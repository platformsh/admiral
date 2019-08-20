<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DeleteProject;
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
 *
 * Error handling is generally "log and fail silently", because the Message Bus
 * is designed to be asynchronous.  It may be running in a queue long after
 * the UI operation that triggered it, so there's no way to send notifications
 * back up.
 *
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

        if (!$pshProject) {
            // If the project doesn't exist, but the user asked for it to be deleted,
            // then the desired post-condition is already met.  This is not an
            // error condition, so just finish silently.
            return;
        }

        try {
            $subscriptionId = $pshProject->getSubscriptionId();
            $sub = $this->client->getSubscription($subscriptionId);
            if (!$sub) {
                return;
            }
            $sub->delete();
        } catch (\RuntimeException $e) {
            // This happens if the subscription is not found. That indicates a
            // problem on the server side that should already be logged, so
            // just continue silently here.
        }
    }
}
