<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Message\UpdateProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Platformsh\Client\Model\Environment;
use Platformsh\Client\Model\Project as PshProject;
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

    public function __construct(PlatformClient $client, EntityManagerInterface $em)
    {
        $this->client = $client;
        $this->em = $em;
    }

    /**
     * Executes an UpdateProject message to cause a Platform.sh Project to self-update.
     *
     * @param UpdateProject $message
     */
    public function __invoke(UpdateProject $message)
    {
        $id = $message->getProjectId();

        $project = $this->em->getRepository(Project::class)->find($id);

        $pshProject = $this->client->getProject($project->getProjectId());

        $archetype = $project->getArchetype();

        $env = $this->ensureEnvironment($pshProject, $archetype->getUpdateBranch());

        $env->runSourceOperation($archetype->getUpdateOperation());
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
    protected function ensureEnvironment(PshProject $pshProject, string $branch) : Environment
    {
        $env = $pshProject->getEnvironment($branch);

        if ($env) {
            if (!$env->isActive()) {
                $env->activate();
            }
            else {
                $env->synchronize(true, true);
            }
        }
        else {
            // Branch
            $pshProject->getEnvironment('master')->branch($branch);
            $env = $pshProject->getEnvironment($branch);
        }

        return $env;
    }
}
