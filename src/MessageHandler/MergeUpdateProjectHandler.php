<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Message\MergeUpdateProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Platformsh\Client\Model\Environment;
use Platformsh\Client\Model\Project as PshProject;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for triggering a merge of the code update branch on a project.
 *
 * This class makes a series of asynchronous calls to the Platform.sh API.
 * It is reasonably fast but does take non-trivial time.  If triggering updates
 * to a handful of projects it should be sufficient to run synchronously.  If
 * trying to update more than a dozen or so projects at once it would be wise
 * to switch to an asynchronous message bus transport.  Consult the Symfony
 * documentation for how to do so.
 */
class MergeUpdateProjectHandler implements MessageHandlerInterface
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
     * Executes a MergeUpdateProject message to cause a Platform.sh Project to self-update.
     *
     * @param MergeUpdateProject $message
     */
    public function __invoke(MergeUpdateProject $message)
    {
        $project = $this->em->getRepository(Project::class)->find($message->getProjectId());
        $pshProject = $this->client->getProject($project->getProjectId());
        $archetype = $project->getArchetype();

        if ($env = $this->getActiveEnvironment($pshProject, $archetype->getUpdateBranch())) {
            $env->merge();
        }
    }

    /**
     * Retrieves an environment object if it's in a mergeable state.
     *
     * @param PshProject $pshProject
     *   The project on which we want to merge an environment.
     * @param string $branch
     *   The branch to merge.
     * @return Environment|null
     *   The Psh Environment object, or null if the environment does not exist
     *   or is not active.
     */
    protected function getActiveEnvironment(PshProject $pshProject, string $branch) : ?Environment
    {
        $env = $pshProject->getEnvironment($branch);

        if ($env && $env->isActive() && $env->operationAvailable('merge')) {
            return $env;
        }

        return null;
    }
}
