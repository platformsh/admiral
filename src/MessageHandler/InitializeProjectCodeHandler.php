<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Message\InitializeProjectCode;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handles initializing code in a project.
 *
 * A project's initial code is based on its archetype.
 *
 * The current implementation just uses the existing Platform.sh
 * "initialize" command.  That is easy but means projects do not
 * share a Git history with their upstream repository.  However,
 * that can be worked around in the update operation.
 *
 * See CloneProjectCodeHandler for an alternative approach.
 *
 * Error handling is generally "log and fail silently", because the Message Bus
 * is designed to be asynchronous.  It may be running in a queue long after
 * the UI operation that triggered it, so there's no way to send notifications
 * back up.
 */
class InitializeProjectCodeHandler implements MessageHandlerInterface
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
     * Execute the command to populate a newly created project with code from its archetype.
     *
     * @param InitializeProjectCode $message
     */
    public function __invoke(InitializeProjectCode $message)
    {
        $archetype = $this->em->getRepository(Archetype::class)->find($message->getArchetypeId());
        if (is_null($archetype)) {
            $this->logger->error('Cannot merge updates for project {pshProjectId}. The archetype is missing.', [
                'pshProjectId' => $message->getPshProjectId(),
            ]);
            return;
        }

        $pshProject = $this->client->getProject($message->getPshProjectId());
        if (!$pshProject) {
            $this->logger->error('Platform.sh project {pshProjectId} not found', [
                'pshProjectId' => $message->getPshProjectId(),
            ]);
            return;
        }

        $pshProject->getEnvironment('master')->initialize($archetype->getName(), $archetype->getGitUri());
    }
}
