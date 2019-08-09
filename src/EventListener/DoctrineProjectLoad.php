<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Project;
use App\PlatformClient;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;

class DoctrineProjectLoad
{
    /**
     * @var PlatformClient
     */
    protected $client;

    /**
     * @var MessageBusInterface
     */
    protected $messageBus;

    /**
     * Cached array info as returned by the Client API.
     *
     * @var array
     */
    protected $accountInfo;

    public function __construct(PlatformClient $client, MessageBusInterface $messageBus)
    {
        $this->client = $client;
        $this->messageBus = $messageBus;
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
            'pshProjectEnvironmentUrl'
        ];
        foreach ($lazyCallbacks as $callback) {
            $project->setCallback($callback, function (...$args) use ($project, $callback) {
                return $this->$callback($project, ...$args);
            });
        }
    }

    protected function pshProjectUrl(Project $project) : string
    {
        return sprintf('https://console.platform.sh/%s/%s', $this->accountInfo()['username'], $project->getProjectId());
    }

    public function pshProjectEnvironmentUrl(Project $project, string $name) : string
    {
        $pshProjectId = $project->getProjectId();
        $pshProject = $this->client->getProject($pshProjectId);
        $urls = $pshProject->getEnvironment($name)->getRouteUrls();
        if (count($urls) == 0) {
            return '';
        }
        return current($urls);
    }


    protected function accountInfo() : array
    {
        if (empty($this->accountInfo)) {
            // This is wrong as it assumes the current user is also the project owner.
            // That is usually the case, but not guaranteed.
            $this->accountInfo = $this->client->getAccountInfo();
        }

        return $this->accountInfo;
    }
}
