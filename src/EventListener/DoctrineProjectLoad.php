<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DoctrineProjectLoad
{

    protected const ACTIVITY_COUNT = 10;

    /**
     * @var PlatformClient
     */
    protected $client;

    /**
     * @var MessageBusInterface
     */
    protected $messageBus;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(PlatformClient $client, MessageBusInterface $messageBus, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    /**
     * Acts on an object just after it has been loaded.
     *
     * Note: This may have an N+1 problem.
     *
     * @param Project $project
     * @param LifecycleEventArgs $args
     */
    public function postLoad(Project $project, LifecycleEventArgs $args)
    {
        $lazyCallbacks = [
            'pshProjectUrl',
            'pshProjectEnvironmentUrl',
            'updateEnvironmentUrl',
            'recentActivities',
            'planSize',
        ];
        foreach ($lazyCallbacks as $callback) {
            $project->setCallback($callback, function (...$args) use ($project, $callback) {
                return $this->$callback($project, ...$args);
            });
        }
    }

    protected function planSize(Project $project) : string
    {
        $pshProject = $this->client->getProject($project->getProjectId());
        $subId = $pshProject->getSubscriptionId();
        $subscription = $this->client->getSubscription($subId);

        return trim($subscription->plan);
    }

    protected function recentActivities(Project $project) : iterable
    {
        $pshProject = $this->client->getProject($project->getProjectId());
        $masterBranch = $pshProject->getEnvironment('master');
        $activities = $masterBranch->getActivities(static::ACTIVITY_COUNT);

        return $activities;
    }

    protected function pshProjectUrl(Project $project) : string
    {
        $pshProject = $this->client->getProject($project->getProjectId());
        if (!$pshProject) {
            $this->logger->error('Platform.sh project {pshProjectId} not found for project {title}', [
                'pshProjectId' => $project->getProjectId(),
                'title' => $project->getTitle()
            ]);
            return '';
        }
        return $pshProject->getLink('#ui');
    }

    protected function updateEnvironmentUrl(Project $project) : string
    {
        return $this->pshProjectEnvironmentUrl($project, $project->getArchetype()->getUpdateBranch());
    }

    public function pshProjectEnvironmentUrl(Project $project, string $name) : string
    {
        $pshProjectId = $project->getProjectId();
        $pshProject = $this->client->getProject($pshProjectId);
        // If the project doesn't exist, bail out now with an empty URL.
        if (!$pshProject) {
            return '';
        }
        $env = $pshProject->getEnvironment($name);
        // If the environment doesn't exist, bail out now with an empty URL.
        if (!$env) {
            return '';
        }

        $urls = $env->getRouteUrls();
        if (count($urls) == 0) {
            return '';
        }
        return current($urls);
    }
}
