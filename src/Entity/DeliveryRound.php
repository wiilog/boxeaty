<?php

namespace App\Entity;

use App\Repository\DeliveryRoundRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRoundRepository::class)
 */
class DeliveryRound {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="deliveryRounds")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $deliverer = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $deliveryMode = null;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="deliveryRounds")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Depository $depository = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $cost = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $distance = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): self {
        $this->status = $status;
        return $this;
    }

    public function getDeliverer(): ?User {
        return $this->deliverer;
    }

    public function setDeliverer(?User $deliverer): self {
        if ($this->deliverer && $this->deliverer !== $deliverer) {
            $this->deliverer->removeDeliveryRound($this);
        }
        $this->deliverer = $deliverer;
        if ($deliverer) {
            $deliverer->addDeliveryRound($this);
        }

        return $this;
    }

    public function getDeliveryMode(): ?string {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(string $deliveryMode): self {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getDepository(): ?Depository {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if ($this->depository && $this->depository !== $depository) {
            $this->depository->removeDeliveryRound($this);
        }
        $this->depository = $depository;
        if ($depository) {
            $depository->addDeliveryRound($this);
        }

        return $this;
    }

    public function getCost(): ?string {
        return $this->cost;
    }

    public function setCost(string $cost): self {
        $this->cost = $cost;

        return $this;
    }

    public function getDistance(): ?string {
        return $this->distance;
    }

    public function setDistance(string $distance): self {
        $this->distance = $distance;

        return $this;
    }

}
