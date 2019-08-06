<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

class DoctrineProject
{
    /**
     * @var PlatformClient
     */
    protected $client;

    public function __construct(PlatformClient $client)
    {
        $this->client = $client;
    }

    /**
     * Acts on an object before its initial save.
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
        // variables based on the project Archetype.  These will be needed by the source operations.
        $archetype = $project->getArchetype();
        $pshProject = $this->client->getProject($project->getProjectId());

        $pshProject->setVariable('env:UPDATE_REMOTE', $archetype->getGitUri());
        $pshProject->setVariable('env:UPDATE_BRANCH', $archetype->getUpdateBranch());
        $pshProject->setVariable('env:UPDATE_OPERATION', $archetype->getUpdateOperation());

        // Initialize the project from the Archetype's Git repository.
        // This will result in distinct Git histories as the initialize command
        // does not preserve the source's history. However, that can be worked around
        // in the update operation.
        // An alternative would be to manually run a Git clone of the Archetype repository
        // and manually push it to the newly created project.
        $pshProject->getEnvironment('master')->initialize($archetype->getName(), $archetype->getGitUri());
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
        $projectId = $project->getProjectId();
        $pshProject = $this->client->getProject($projectId);
        $subscriptionId = $pshProject->getSubscriptionId();
        $this->client->getSubscription($subscriptionId)->delete();
    }
}
