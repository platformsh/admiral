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
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        /** @var Project $project */
        $project = $args->getObject();

        // This event listener triggers for all saved documents, but we care only about Projects.
        if (! $project instanceof Project) {
            return;
        }

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
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        /** @var Project $project */
        $project = $args->getObject();

        // This event listener triggers for all saved documents, but we care only about Projects.
        if (! $project instanceof Project) {
            return;
        }

        // Now that the project has been created on Platform.sh, set its environment
        // variables based on the project Archetype.  These will be needed by the source operations.
        $archetype = $project->getArchetype();
        $pshProject = $this->client->getProject($project->getProjectId());

        $pshProject->setVariable('env:UPDATE_REMOTE', $archetype->getGitUri());
        $pshProject->setVariable('env:UPDATE_BRANCH', $archetype->getUpdateBranch());
        $pshProject->setVariable('env:UPDATE_OPERATION', $archetype->getUpdateOperation());
    }
}
