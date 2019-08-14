<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Message component for a local Project ID.
 */
trait ProjectId
{
    /**
     * @var int
     */
    protected $projectId;


    public function getProjectId(): int
    {
        return $this->projectId;
    }
}