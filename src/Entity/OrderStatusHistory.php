<?php

namespace App\Entity;

use App\Repository\OrderStatusHistoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderStatusHistoryRepository::class)
 */
class OrderStatusHistory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="date")
     */
    private $changement;

    /**
     * @ORM\Column(type="text")
     */
    private $justification;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="orderStatusHistories")
     */
    private ?User $user;

    /**
     * @ORM\ManyToOne (targetEntity=Status::class, inversedBy="orderStatusHistory")
     */
    private ?Status $status;

    /**
     * @ORM\OneToOne(targetEntity=Order::class, inversedBy="orderStatusHistory", cascade={"persist", "remove"})
     */
    private ?Order $orderId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChangement(): ?\DateTimeInterface
    {
        return $this->changement;
    }

    public function setChangement(\DateTimeInterface $changement): self
    {
        $this->changement = $changement;

        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(string $justification): self
    {
        $this->justification = $justification;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        if($this->status && $this->status !== $status) {
            $this->status->removeOrderStatusHistory($this);
        }
        $this->status = $status;
        if($status) {
            $status->addOrderStatusHistory($this);
        }

        return $this;
    }

    public function getOrderId(): ?Order
    {
        return $this->orderId;
    }

    public function setOrderId(?Order $orderId): self
    {
        if($this->orderId && $this->orderId->getOrderStatusHistory() === $this) {
            $this->orderId->setOrderStatusHistory(null);
        }
        $this->orderId = $orderId;
        if($orderId) {
            $orderId->setOrderStatusHistory($this);
        }

        return $this;
    }


}
