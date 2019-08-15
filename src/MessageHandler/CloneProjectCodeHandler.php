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
 * An alternative would be to manually run a Git clone of the
 * Archetype repository and manually push it to the newly created
 * project.  That would be considerably more work, however.
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
            // This means the archetype was deleted between when this command was requested
            // and now. Log it and move on since there's not much else we can do.
            $this->logger->error('Cannot clone code to project {pshProjectId}. The archetype is missing.', [
                'pshProjectId' => $message->getPshProjectId(),
            ]);
            return;
        }

        $pshProject = $this->client->getProject($message->getPshProjectId());

        // ...
    }
}
