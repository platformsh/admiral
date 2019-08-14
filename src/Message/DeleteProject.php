<?php
declare(strict_types=1);

namespace App\Message;

/**
 * Message to request deletion of a project.
 *
 * Because the local Project is already deleted at this point,
 * we must pass the Platform.sh Project ID forward.  The local Project ID
 * is no longer available.
 */
class DeleteProject
{
    /**
     * @var int
     */
    protected $pshProjectId;

    public function __construct(string $pshProjectId)
    {
        $this->pshProjectId = $pshProjectId;
    }

    public function getPshProjectId(): string
    {
        return $this->pshProjectId;
    }
}
