<?php
declare(strict_types=1);

namespace App\Message;

class SynchronizeProject
{
    /**
     * @var int
     */
    protected $projectId;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
    }

    public function getProjectId(): int
    {
        return $this->projectId;
    }
}
