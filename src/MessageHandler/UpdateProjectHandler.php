<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Message\UpdateProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Platformsh\Client\Model\Environment;
use Platformsh\Client\Model\Project as PshProject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for triggering code updates on a project.
 *
 * This class makes a series of asynchronous calls to the Platform.sh API.
 * It is reasonably fast but does take non-trivial time.  If triggering updates
 * to a handful of projects it should be sufficient to run synchronously.  If
 * trying to update more than a dozen or so projects at once it would be wise
 * to switch to an asynchronous message bus transport.  Consult the Symfony
 * documentation for how to do so.
 *
 * Error handling is generally "log and fail silently", because the Message Bus
 * is designed to be asynchronous.  It may be running in a queue long after
 * the UI operation that triggered it, so there's no way to send notifications
 * back up.
 */
class UpdateProjectHandler implements MessageHandlerInterface
{
    /**
     * @var PlatformClient
     */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(PlatformClient $client, EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * Executes an UpdateProject message to cause a Platform.sh Project to self-update.
     *
     * @param UpdateProject $message
     */
    public function __invoke(UpdateProject $message)
    {
        $project = $this->em->getRepository(Project::class)->find($message->getProjectId());
        if (is_null($project)) {
            $this->logger->error('Update requested for project {id}, but that project no longer exists.', [
                'id' => $message->getProjectId()
            ]);
            return;
        }

        $pshProject = $this->client->getProject($project->getProjectId());
        if (!$pshProject) {
            $this->logger->error('Platform.sh project {pshProjectId} not found for project {title}', [
                'pshProjectId' => $project->getProjectId(),
                'title' => $project->getTitle()
            ]);
            return;
        }

        $archetype = $project->getArchetype();
        if (is_null($archetype)) {
            $this->logger->error('Cannot merge updates for project project {pshProjectId}. The archetype is missing.', [
                'pshProjectId' => $pshProject->id,
            ]);
            return;
        }

        if ($env = $this->ensureEnvironment($pshProject, $archetype->getUpdateBranch())) {
            $env->runSourceOperation($archetype->getUpdateOperation());
        }
    }

    /**
     * Ensures that an environment is up active and recently updated.
     *
     * All of the API calls this method makes are asynchronous and don't block
     * queuing a source operation call.  That is, when this method is complete
     * the environment may not be active, yet, but there will be appropriate
     * actions enqueued so that it will be by the time a future-queued source
     * operation executes.
     *
     * This process also runs a code-and-data sync from the parent branch if necessary,
     * so that it's in the same state as if it were just created.
     *
     * @param PshProject $pshProject
     *   The project on which we want a working environment.
     * @param string $branch
     *   The branch to ensure exists.
     * @return Environment
     *   The Psh Environment object, now assured to be in a ready state.
     */
    protected function ensureEnvironment(PshProject $pshProject, string $branch) : ?Environment
    {
        $env = $pshProject->getEnvironment($branch);

        if ($env) {
            if (!$env->isActive()) {
                // The branch already exists but there's no running environment.
                // Just turn it on.
                $env->activate();
            }
            else {
                // The environment already exists.  Synchronize its code/data
                // from production first.
                $env->synchronize(true, true);
            }
        }
        else {
            // Make a new branch from scratch.
            $masterBranch = $pshProject->getEnvironment('master');

            if ($masterBranch->deployment_target !== 'local') {
                // The above guard protects against usage on Dedicated clusters.
                // In theory that should never happen, but...
                $this->logger->error('Attempted to update project {title} ({pshProjectId}), but it is a Dedicated Enterprise cluster.', [
                    'title' => $pshProject->title,
                    'pshProjectId' => $pshProject->id,
                ]);
                return null;
            }

            if (!$masterBranch->has_code) {
                // This guard protects against an uninitialized project.
                // Again, this should be impossible but...
                $this->logger->error('Attempted to update project {title} ({pshProjectId}), but it has not been initalized.', [
                    'title' => $pshProject->title,
                    'pshProjectId' => $pshProject->id,
                ]);
                return null;
            }

            // Wait for the branch operation to be available.  If it still isn't after
            // 30 seconds, just give up.
            // This normally only happens if you try to trigger an update very quickly
            // after creating the project, or if master is in the middle of a deploy.
            $timer = 0;
            while(!$masterBranch->operationAvailable('branch', true)) {
                sleep(1);
                if ($timer++ > 30) {
                    return null;
                }
            }

            $masterBranch->branch($branch);
            $env = $pshProject->getEnvironment($branch);
        }

        return $env;
    }
}
