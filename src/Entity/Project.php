<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ProjectRepository")
 */
class Project
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
    private $title;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Archetype", inversedBy="projects")
     * @ORM\JoinColumn(nullable=false)
     */
    private $archetype;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $projectId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $region;

    /**
     * A collection of lazy-callbacks this object may invoke.
     *
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * Adds a lazy callback.
     *
     * This method should be called from a postLoad() listener in order to
     * add service-using lazy methods to the object.  Explicit methods on
     * this object can then call those callbacks by matching on the string
     * name of the method/callback.
     *
     * @param string $name
     * @param callable $callback
     */
    public function setCallback(string $name, callable $callback)
    {
        $this->callbacks[$name] = $callback;
    }

    /**
     * Returns the URL to the management console for the project.
     *
     * @return string
     */
    public function pshProjectUrl() : string
    {
        return $this->callbacks[__FUNCTION__]();
    }

    /**
     * Returns the URL to a given environment on the corresponding Platform.sh Project.
     *
     * @param string $name
     *   The name of the environment.
     * @return string
     *   The URL to the environment.
     */
    public function pshProjectEnvironmentUrl(string $name) : string
    {
        return $this->callbacks[__FUNCTION__]($name);
    }

    public function updateEnvironmentUrl() : string
    {
        return $this->callbacks[__FUNCTION__]();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getArchetype(): ?Archetype
    {
        return $this->archetype;
    }

    public function setArchetype(?Archetype $archetype): self
    {
        $this->archetype = $archetype;

        return $this;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function setProjectId(string $projectId): self
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function __toString()
    {
        return $this->getTitle();
    }
}
