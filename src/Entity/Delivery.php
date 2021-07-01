<?php

namespace App\Entity;

use App\Repository\DeliveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRepository::class)
 */
class Delivery
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
     * @ORM\ManyToOne(targetEntity=Status::class, inversedBy="delivery")
     */
    private ?Status $status;

    /**
     * @ORM\OneToOne(targetEntity=Order::class, inversedBy="delivery", cascade={"persist", "remove"})
     */
    private ?Order $orderId;

    /**
     * @ORM\OneToOne(targetEntity=Preparation::class, inversedBy="delivery")
     */
    private ?Preparation $preparation;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, inversedBy="deliveries")
     */
    private ?Attachment $signature;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class, inversedBy="deliveries")
     */
    private ?Attachment $photo;

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

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        if($this->status && $this->status !== $status) {
            $this->status->removeDelivery($this);
        }
        $this->status = $status;
        if($status) {
            $status->addDelivery($this);
        }

        return $this;
    }

    public function getOrderId(): ?Order
    {
        return $this->orderId;
    }

    public function setOrderId(?Order $orderId): self
    {
        if($this->orderId && $this->orderId->getDelivery() === $this) {
            $this->orderId->setDelivery(null);
        }
        $this->orderId = $orderId;
        if($orderId) {
            $orderId->setDelivery($this);
        }

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        if($this->preparation && $this->preparation->getDelivery() === $this) {
            $this->preparation->setDelivery(null);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->setDelivery($this);
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
            $this->signature->removeDelivery($this);
        }
        $this->signature = $signature;
        if($signature) {
            $signature->addDelivery($this);
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
            $this->photo->removeDelivery($this);
        }
        $this->photo = $photo;
        if($photo) {
            $photo->addDelivery($this);
        }

        return $this;
    }
}
