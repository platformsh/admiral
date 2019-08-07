<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Message\SetProjectVariables;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;


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

    public function __construct(PlatformClient $client, EntityManagerInterface $em)
    {
        $this->client = $client;
        $this->em = $em;
    }

    /**
     * Executes an SetProjectVariables message to set/update the management variables on a project.
     *
     * @param SetProjectVariables $message
     */
    public function __invoke(SetProjectVariables $message)
    {
        $archetype = $this->em->getRepository(Archetype::class)->find($message->getArchetypeId());

        $pshProject = $this->client->getProject($message->getPshProjectId());

        $pshProject->setVariable('env:UPDATE_REMOTE', $archetype->getGitUri());
        $pshProject->setVariable('env:UPDATE_BRANCH', $archetype->getUpdateBranch());
        $pshProject->setVariable('env:UPDATE_OPERATION', $archetype->getUpdateOperation());

    }
}
