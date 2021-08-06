<?php

namespace App\Entity;

use App\Repository\CollectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollectRepository::class)
 */
class Collect {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrder::class, inversedBy="collect")
     */
    private ?ClientOrder $order = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="collects")
     */
    private ?Location $location = null;

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
     */
    private Collection $boxes;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $signature = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $photo = null;

    public function __construct() {
        $this->boxes = new ArrayCollection();
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

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        if ($this->location && $this->location !== $location) {
            $this->location->removeCollect($this);
        }
        $this->location = $location;
        if ($location) {
            $location->addCollect($this);
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

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getCollect() === $this) {
            $this->order->setCollect(null);
        }
        $this->order = $order;
        if ($order) {
            $order->setCollect($this);
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection {
        return $this->boxes;
    }

    public function addBox(Box $box): self {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->addCollect($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            $box->removeCollect($this);
        }

        return $this;
    }

    public function setBoxes(?array $boxes): self {
        foreach ($this->getBoxes()->toArray() as $box) {
            $this->removeBox($box);
        }

        $this->boxes = new ArrayCollection();
        foreach ($boxes as $box) {
            $this->addBox($box);
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

}
