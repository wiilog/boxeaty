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
     * @ORM\OneToOne(targetEntity=Preparation::class, inversedBy="delivery")
     */
    private ?Preparation $preparation = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class)
     */
    private ?Attachment $signature = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class)
     */
    private ?Attachment $photo = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getDelivery() === $this) {
            $this->order->setDelivery(null);
        }
        $this->order = $order;
        if ($order) {
            $order->setDelivery($this);
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

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        if ($this->preparation && $this->preparation->getDelivery() === $this) {
            $this->preparation->setDelivery(null);
        }
        $this->preparation = $preparation;
        if ($preparation) {
            $preparation->setDelivery($this);
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
