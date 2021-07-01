<?php

namespace App\Entity;

use App\Repository\CollectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollectRepository::class)
 */
class Collect
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $token;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="collects")
     */
    private ?Location $location;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class, inversedBy="collects")
     */
    private ?Status $status;

    /**
     * @ORM\OneToOne(targetEntity=Order::class, inversedBy="collect", cascade={"persist", "remove"})
     */
    private ?Order $orderId;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="collects")
     */
    private Collection $boxes;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, inversedBy="collects")
     */
    private ?Attachment $signature;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, inversedBy="collects")
     */
    private ?Attachment $photo;

    public function __construct()
    {
        $this->boxes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?int
    {
        return $this->token;
    }

    public function setToken(int $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): self
    {
        if($this->location && $this->location !== $location){
            $this->location->removeCollect($this);
        }
        $this->location = $location;
        if($location){
            $location->addCollect($this);
        }

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): self
    {
        if($this->status && $this->status !== $status){
            $this->status->removeCollect($this);
        }
        $this->status = $status;
        if($status){
            $status->addCollect($this);
        }

        return $this;
    }

    public function getOrderId(): ?Order
    {
        return $this->orderId;
    }

    public function setOrderId(?Order $orderId): self
    {
        if($this->orderId && $this->orderId->getCollect() === $this) {
            $this->orderId->setCollect(null);
        }
        $this->orderId = $orderId;
        if($orderId) {
            $orderId->setCollect($this);
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection
    {
        return $this->boxes;
    }

    public function addBox(Box $box): self
    {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->addCollect($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self
    {
        if ($this->boxes->removeElement($box)) {
            $box->removeCollect($this);
        }

        return $this;
    }

    public function setBox(?array $boxes): self {
        foreach($this->getBoxes()->toArray() as $box) {
            $this->removeBox($box);
        }

        $this->boxes = new ArrayCollection();
        foreach($boxes as $box) {
            $this->addBox($box);
        }

        return $this;
    }

    public function getSignature(): ?Attachment
    {
        return $this->signature;
    }

    public function setSignature(?Attachment $signature): self
    {
        if($this->signature && $this->signature !== $signature) {
            $this->signature->removeCollect($this);
        }
        $this->signature = $signature;
        if($signature) {
            $signature->addCollect($this);
        }

        return $this;
    }

    public function getPhoto(): ?Attachment
    {
        return $this->photo;
    }

    public function setPhoto(?Attachment $photo): self
    {
        if($this->photo && $this->photo !== $photo) {
            $this->photo->removeCollect($this);
        }
        $this->photo = $photo;
        if($photo) {
            $photo->addCollect($this);
        }

        return $this;
    }
}
