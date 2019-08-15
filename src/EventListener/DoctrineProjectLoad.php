<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Platformsh\Client\Model\Environment;
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
            'updateEnvironment',
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

    protected function updateEnvironment(Project $project) : ?Environment
    {
        return $this->pshProjectEnvironment($project, $project->getArchetype()->getUpdateBranch());
    }

    protected function pshProjectEnvironment(Project $project, string $name) : ?Environment
    {
        $pshProject = $this->client->getProject($project->getProjectId());
        if (!$pshProject) {
            return null;
        }
        return $pshProject->getEnvironment($name) ?: null;
    }

    public function pshProjectEnvironmentUrl(Project $project, string $name) : string
    {
        $env = $this->pshProjectEnvironment($project, $name);
        if (is_null($env)) {
            return '';
        }

        $urls = $env->getRouteUrls();
        if (count($urls) == 0) {
            return '';
        }
        return current($urls);
    }
}
