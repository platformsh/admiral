<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Body for any Message that consists solely of a local Project ID.
 */
trait ProjectMessage
{
    use ProjectId;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
    }
}
