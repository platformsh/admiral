<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Message\SynchronizeProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for synchronizing project data.
 */
class SynchronizeProjectHandler implements MessageHandlerInterface
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
     * Executes a SynchronizeProjectHandler command.
     *
     * @param SynchronizeProject $message
     */
    public function __invoke(SynchronizeProject $message)
    {
        $project = $this->em->getRepository(Project::class)->find($message->getProjectId());
        if (is_null($project)) {
            // This means the project was deleted sometime between when the merge was requested
            // and now.  If that's the case then just log it and forget about it, since there's
            // not much else to do.
            $this->logger->error('Configuration sync requested for project {id}, but that project no longer exists.', [
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

        // The only editable part of a Project record locally is the title.
        // Keep that in sync with Platform.sh's project.
        $pshProject->update(['title' => $project->getTitle()]);
    }
}
