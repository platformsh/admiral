<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Message\SetProjectVariables;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for setting project variables.
 *
 * Various variables on a project need to be set based on the project's Archetype,
 * and kept up to date.  This command resynchronizes those variables.
 */
class SetProjectVariablesHandler implements MessageHandlerInterface
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
     * Executes an SetProjectVariables message to set/update the management variables on a project.
     *
     * @param SetProjectVariables $message
     */
    public function __invoke(SetProjectVariables $message)
    {
        $archetype = $this->em->getRepository(Archetype::class)->find($message->getArchetypeId());
        if (is_null($archetype)) {
            // This means the archetype was deleted between when this command was requested
            // and now. Log it and move on since there's not much else we can do.
            $this->logger->error('Cannot update project {pshProjectId} from archetype {archetypeId}. The archetype is missing.', [
                'pshProjectId' => $message->getPshProjectId(),
                'archetypeId' => $message->getArchetypeId(),
            ]);
            return;
        }
        $pshProject = $this->client->getProject($message->getPshProjectId());
        if (!$pshProject) {
            // This means the project was deleted between when this command was requested
            // and now. Log it and move on since there's not much else we can do.
            $this->logger->error('Cannot update project {pshProjectId} from archetype {archetypeId}. The project does not exist.', [
                'pshProjectId' => $message->getPshProjectId(),
                'archetypeId' => $message->getArchetypeId(),
            ]);
            return;
        }

        $pshProject->setVariable('env:UPDATE_REMOTE', $archetype->getGitUri());
        $pshProject->setVariable('env:UPDATE_BRANCH', $archetype->getUpdateBranch());
        $pshProject->setVariable('env:UPDATE_OPERATION', $archetype->getUpdateOperation());
    }
}
