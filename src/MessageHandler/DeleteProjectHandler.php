<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Archetype;
use App\Entity\Project;
use App\Message\DeleteProject;
use App\Message\SetProjectVariables;
use App\Message\SynchronizeProject;
use App\PlatformClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Handler for deleting project data.
 *
 * When a project is deleted, delete the corresponding Platform.sh project.
 *
 * Note: The EasyAdminBundle's deletion verification warning should probably
 * be made scarier, given that this is a very destructive operation.
 */
class DeleteProjectHandler implements MessageHandlerInterface
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
     * @param DeleteProject $message
     */
    public function __invoke(DeleteProject $message)
    {
        $pshProject = $this->client->getProject($message->getPshProjectId());

        $subscriptionId = $pshProject->getSubscriptionId();
        $this->client->getSubscription($subscriptionId)->delete();
    }
}
