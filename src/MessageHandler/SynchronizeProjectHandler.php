<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Entity\Project;
use App\Message\SetProjectVariables;
use App\Message\SynchronizeProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
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

    public function __construct(PlatformClient $client, EntityManagerInterface $em)
    {
        $this->client = $client;
        $this->em = $em;
    }

    /**
     * Executes a SynchronizeProjectHandler command.
     *
     * @param SynchronizeProject $message
     */
    public function __invoke(SynchronizeProject $message)
    {
        $project = $this->em->getRepository(Project::class)->find($message->getProjectId());
        $pshProject = $this->client->getProject($project->getProjectId());

        // The only editable part of a Project record locally is the title.
        // Keep that in sync with Platform.sh's project.
        $pshProject->update(['title' => $project->getTitle()]);
    }
}
