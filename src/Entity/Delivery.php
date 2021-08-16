<?php

namespace App\Entity;

use App\Repository\DeliveryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRepository::class)
 */
class Delivery {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrder::class, inversedBy="delivery")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $order = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     */
    private ?Status $status = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $tokens = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $distance = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $signature = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, cascade={"persist", "remove"})
     */
    private ?Attachment $photo = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $deliveredAt;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getDelivery() !== $this) {
            $oldPreparation = $this->order;
            $this->order = null;
            $oldPreparation->setDelivery(null);
        }
        $this->order = $order;
        if ($this->order && $this->order->getDelivery() !== $this) {
            $this->order->setDelivery($this);
        }

        return $this;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(Status $status): self {
        $this->status = $status;
        return $this;
    }

    public function getTokens(): ?int {
        return $this->tokens;
    }

    public function setTokens(int $tokens): self {
        $this->tokens = $tokens;

        return $this;
    }

    public function getDistance(): ?string {
        return $this->distance;
    }

    public function setDistance(?string $distance): self {
        $this->distance = $distance;
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

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

}
