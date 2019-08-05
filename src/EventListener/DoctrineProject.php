<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

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
}
