<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Command message indicating a project's master environment should be backed up.
 */
class BackupProduction
{
    /**
     * @var int
     */
    protected $projectId;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
    }

    public function getProjectId() : int
    {
        return $this->projectId;
    }
}
