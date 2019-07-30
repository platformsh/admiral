<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ArchetypeRepository")
 */
class Archetype
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $gitUri;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Project", mappedBy="archetype")
     */
    private $projects;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $updateBranch;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $updateOperation;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getGitUri(): ?string
    {
        return $this->gitUri;
    }

    public function setGitUri(string $gitUri): self
    {
        $this->gitUri = $gitUri;

        return $this;
    }

    /**
     * @return Collection|Project[]
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setArchetype($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            $this->projects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getArchetype() === $this) {
                $project->setArchetype(null);
            }
        }

        return $this;
    }

    public function getUpdateBranch(): ?string
    {
        return $this->updateBranch;
    }

    public function setUpdateBranch(string $updateBranch): self
    {
        $this->updateBranch = $updateBranch;

        return $this;
    }

    public function getUpdateOperation(): ?string
    {
        return $this->updateOperation;
    }

    public function setUpdateOperation(?string $updateOperation): self
    {
        $this->updateOperation = $updateOperation;

        return $this;
    }

    public function __toString()
    {
        return $this->getName();
    }
}
