<?php

namespace App\Entity;

use App\Repository\CollectRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollectRepository::class)
 */
class Collect {

    const PREFIX_NUMBER = 'C';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="pickedCollects")
     */
    private ?Location $pickLocation = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="droppedCollects")
     */
    private ?Location $dropLocation = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     */
    private ?Status $status = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $tokens = null;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="collects")
     * @ORM\JoinTable(name="collect_crate")
     */
    private Collection $crates;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $signature = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $photo = null;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    private ?DateTime $createdAt = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $number = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $treatedAt = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="collects")
     */
    private ?User $operator = null;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="collects")
     */
    private ?Client $client = null;

    public function __construct() {
        $this->crates = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getTokens(): ?int {
        return $this->tokens;
    }

    public function setTokens(int $tokens): self {
        $this->tokens = $tokens;

        return $this;
    }

    public function getPickLocation(): ?Location {
        return $this->pickLocation;
    }

    public function setPickLocation(?Location $pickLocation): self {
        if ($this->pickLocation && $this->pickLocation !== $pickLocation) {
            $this->pickLocation->removePickedCollect($this);
        }
        $this->pickLocation = $pickLocation;
        if ($pickLocation) {
            $pickLocation->addPickedCollect($this);
        }

        return $this;
    }

    public function getDropLocation(): ?Location {
        return $this->dropLocation;
    }

    public function setDropLocation(?Location $dropLocation): self {
        if ($this->dropLocation && $this->dropLocation !== $dropLocation) {
            $this->dropLocation->removeDroppedCollect($this);
        }
        $this->dropLocation = $dropLocation;
        if ($dropLocation) {
            $dropLocation->addDroppedCollect($this);
        }

        return $this;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): self {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getCrates(): Collection {
        return $this->crates;
    }

    public function addCrate(Box $crate): self {
        if (!$this->crates->contains($crate)) {
            $this->crates[] = $crate;
            $crate->addCollect($this);
        }

        return $this;
    }

    public function removeCrate(Box $crate): self {
        if ($this->crates->removeElement($crate)) {
            $crate->removeCollect($this);
        }

        return $this;
    }

    public function setCrates(?array $crates): self {
        foreach ($this->getCrates()->toArray() as $crate) {
            $this->removeCrate($crate);
        }

        $this->crates = new ArrayCollection();
        foreach ($crates as $crate) {
            $this->addCrate($crate);
        }

        return $this;
    }

    public function getSignature(): ?Attachment {
        return $this->signature;
    }

    public function setSignature(?Attachment $signature): self {
        $this->signature = $signature;
        return $this;
    }

    public function getPhoto(): ?Attachment {
        return $this->photo;
    }

    public function setPhoto(?Attachment $photo): self {
        $this->photo = $photo;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getTreatedAt(): ?\DateTimeInterface
    {
        return $this->treatedAt;
    }

    public function setTreatedAt(?\DateTimeInterface $treatedAt): self
    {
        $this->treatedAt = $treatedAt;

        return $this;
    }

    public function getOperator(): ?User
    {
        return $this->operator;
    }

    public function setOperator(?User $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

}
