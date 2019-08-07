<?php
declare(strict_types=1);

namespace App\Message;

class SetProjectVariables
{

    protected $archetypeId;

    protected $pshProjectId;

    public function __construct(int $archetypeId, string $pshProjectId)
    {
        $this->archetypeId = $archetypeId;
        $this->pshProjectId = $pshProjectId;
    }

    public function getArchetypeId(): int
    {
        return $this->archetypeId;
    }

    public function getPshProjectId(): string
    {
        return $this->pshProjectId;
    }
}