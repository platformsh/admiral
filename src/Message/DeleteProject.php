<?php
declare(strict_types=1);

namespace App\Message;

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
