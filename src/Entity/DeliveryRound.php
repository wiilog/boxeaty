<?php

namespace App\Entity;

use App\Repository\DeliveryRoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
     * @ORM\Column(type="string")
     */
    private ?string $number = null;

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
     * @ORM\ManyToOne(targetEntity=DeliveryMethod::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?DeliveryMethod $deliveryMethod = null;

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

    /**
     * @ORM\OneToMany(targetEntity=ClientOrder::class, mappedBy="deliveryRound")
     */
    private Collection $orders;

    public function __construct() {
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;
        return $this;
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

    public function getDeliveryMethod(): ?DeliveryMethod {
        return $this->deliveryMethod;
    }

    public function setDeliveryMethod(?DeliveryMethod $deliveryMethod): self {
        $this->deliveryMethod = $deliveryMethod;
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

    /**
     * @return Collection|ClientOrder[]
     */
    public function getOrders(): Collection {
        return $this->orders;
    }

    public function addOrder(ClientOrder $order): self {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setDeliveryRound($this);
        }

        return $this;
    }

    public function removeOrder(ClientOrder $order): self {
        if ($this->orders->removeElement($order)) {
            if ($order->getDeliveryRound() === $this) {
                $order->setDeliveryRound(null);
            }
        }

        return $this;
    }

    public function setOrders(?array $orders): self {
        foreach ($this->getOrders()->toArray() as $order) {
            $this->removeOrder($order);
        }

        $this->orders = new ArrayCollection();
        foreach ($orders as $order) {
            $this->addOrder($order);
        }

        return $this;
    }

}
