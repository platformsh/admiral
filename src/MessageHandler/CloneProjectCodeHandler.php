<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Git\Repository;
use App\Message\CloneProjectCode;
use App\Message\InitializeProjectCode;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handles cloning code in a project.
 *
 * A project's initial code is based on its archetype.
 *
 * This implementation does a manual clone of the archetype's
 * code base into the project repository.  That ensures a common
 * Git history which makes for cleaner merges.
 *
 * Error handling is generally "log and fail silently", because the Message Bus
 * is designed to be asynchronous.  It may be running in a queue long after
 * the UI operation that triggered it, so there's no way to send notifications
 * back up.
 */
class CloneProjectCodeHandler implements MessageHandlerInterface
{

    /**
     * The root directory to hold all of the archetype repos.
     *
     * @todo Make this sensitive to the var directory via configuration
     * in the ParameterBag, or similar, rather than hard coding it with
     * a .. in it.
     */
    const ARCHETYPE_REPOSITORY_DIR = '../var/archetypes';

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

    /**
     * @var string
     */
    protected $repositoryParentDir;

    /**
     * A full file path.
     *
     * @var string
     */
    protected $privateKeyFile;

    public function __construct(PlatformClient $client, EntityManagerInterface $em, LoggerInterface $logger, string $repositoryParentDir, string $privateKeyFile = null)
    {
        $this->client = $client;
        $this->em = $em;
        $this->logger = $logger;
        $this->repositoryParentDir = $repositoryParentDir;
        $this->privateKeyFile = $privateKeyFile;
    }

    /**
     * Execute the command to populate a newly created project with code from its archetype.
     *
     * @param InitializeProjectCode $message
     */
    public function __invoke(CloneProjectCode $message)
    {
        $archetype = $this->em->getRepository(Archetype::class)->find($message->getArchetypeId());
        if (is_null($archetype)) {
            $this->logger->error('Cannot clone code to project {pshProjectId}. The archetype is missing.', [
                'pshProjectId' => $message->getPshProjectId(),
            ]);
            return;
        }

        $pshProject = $this->client->getProject($message->getPshProjectId());

        try {
            $repo = new Repository($this->repositoryParentDir, $archetype->getGitUri(), $this->privateKeyFile, $this->logger);

            $repo->ensureRepository();
            $repo->addRemote($pshProject->id, $pshProject->getGitUrl());
            $repo->pushToRemote($pshProject->id, 'master');
        } catch (\Exception $e) {
            $this->logger->error('Cloning project code from archetype {archetype} to project {pshProjectId} failed: {message}', [
                'archetype' => $archetype->getName(),
                'pshProjectId' => $message->getPshProjectId(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
