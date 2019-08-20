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
 *
 * Error handling is generally "log and fail silently", because the Message Bus
 * is designed to be asynchronous.  It may be running in a queue long after
 * the UI operation that triggered it, so there's no way to send notifications
 * back up.
 *
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

        $archetype = $project->getArchetype();
        if (is_null($archetype)) {
            $this->logger->error('Cannot sync configuration for project {pshProjectId}. The archetype is missing.', [
                'pshProjectId' => $pshProject->id,
            ]);
            return;
        }

        // The only editable part of a Project record locally is the title.
        // Keep that in sync with Platform.sh's project.
        $pshProject->update(['title' => $project->getTitle()]);

        // Update variables on the project, based on the archetype.
        $pshProject->setVariable('env:UPDATE_REMOTE', $archetype->getGitUri());
    }
}
