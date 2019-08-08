<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\Message\InitializeProjectCode;
use App\Message\SetProjectVariables;
use App\Message\SynchronizeProject;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Platformsh\Client\Model\Project as PshProject;
use Symfony\Component\Messenger\MessageBusInterface;

class DoctrineProject
{
    /**
     * @var PlatformClient
     */
    protected $client;

    /**
     * @var MessageBusInterface
     */
    protected $messageBus;

    public function __construct(PlatformClient $client, MessageBusInterface $messageBus)
    {
        $this->client = $client;
        $this->messageBus = $messageBus;
    }

    /**
     * Acts on an object before its initial save.
     *
     * Because these actions must be taken before the Doctrine
     * object is saved, they cannot go out on the message bus
     * as that may be asynchronous. These actions must be taken
     * synchronously.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function prePersist(Project $project, LifecycleEventArgs $args)
    {
        // Create the subscription.
        $subscription = $this->client->createSubscription($project->getRegion(), 'development', $project->getTitle());

        // Pause the process until the subscription is complete.
        // It would be cleaner in the future to make this fully asynchronous, but
        // that will entail quite a bit more work.
        $subscription->wait();

        // Record the subscription's project ID in the local project record.
        // Now in the future we can load the project data on the fly.
        $project->setProjectId($subscription->project_id);
    }

    /**
     * Acts on an object just after it has been saved.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function postPersist(Project $project, LifecycleEventArgs $args)
    {
        // Now that the project has been created on Platform.sh, set its environment
        // variables based on the project Archetype.  These will be needed by the source operation.
        $archetype = $project->getArchetype();

        $this->messageBus->dispatch(new SetProjectVariables($archetype->getId(), $project->getProjectId()));
        $this->messageBus->dispatch(new InitializeProjectCode($archetype->getId(), $project->getProjectId()));
    }

    /**
     * Acts on an object just after it's been deleted.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function postRemove(Project $project, LifecycleEventArgs $args)
    {
        // When a project is deleted, delete the corresponding Platform.sh project.
        // Note: The EasyAdminBundle's deletion verification warning should probably
        // be made scarier, given that this is a very destructive operation.
        $pshProject = $this->getPshProject($project);
        $subscriptionId = $pshProject->getSubscriptionId();
        $this->client->getSubscription($subscriptionId)->delete();
    }

    /**
     * Acts on an object just after it has been updated.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(Project $project, LifecycleEventArgs $args)
    {
        $this->messageBus->dispatch(new SynchronizeProject($project->getId()));
    }

    /**
     * Returns the Platform.sh project object that corresponds to the provided local Project reference.
     *
     * @param Project $project
     * @return PshProject
     */
    protected function getPshProject(Project $project) : PshProject
    {
        return $this->client->getProject($project->getProjectId());
    }
}
