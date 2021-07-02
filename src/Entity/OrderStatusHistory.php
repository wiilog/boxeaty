<?php

namespace App\Entity;

use App\Repository\OrderStatusHistoryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderStatusHistoryRepository::class)
 */
class OrderStatusHistory {
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=ClientOrder::class, inversedBy="orderStatusHistory")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $order = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status = null;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $justification = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="orderStatusHistories")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $user = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $changedAt = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order !== $order) {
            $this->order->removeOrderStatusHistory($this);
        }
        $this->order = $order;
        if ($order) {
            $order->addOrderStatusHistory($this);
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

    public function getJustification(): ?string {
        return $this->justification;
    }

    public function setJustification(string $justification): self {
        $this->justification = $justification;

        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;

        return $this;
    }

    public function getChangedAt(): ?DateTime {
        return $this->changedAt;
    }

    public function setChangedAt(DateTime $changedAt): self {
        $this->changedAt = $changedAt;

        return $this;
    }


}
