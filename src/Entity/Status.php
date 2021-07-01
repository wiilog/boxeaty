<?php

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StatusRepository::class)
 */
class Status
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRound::class, mappedBy="status")
     */
    private Collection $deliveryRounds;

    /**
     * @ORM\OneToMany (targetEntity=OrderStatusHistory::class, mappedBy="status")
     */
    private Collection $orderStatusHistory;

    /**
     * @ORM\OneToMany (targetEntity=Order::class, mappedBy="status")
     */
    private Collection $orders;

    /**
     * @ORM\OneToMany(targetEntity=Collect::class, mappedBy="status")
     */
    private Collection $collects;

    /**
     * @ORM\OneToMany (targetEntity=Delivery::class, mappedBy="status")
     */
    private Collection $delivery;

    public function __construct()
    {
        $this->deliveryRounds = new ArrayCollection();
        $this->orderStatusHistory = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->collects = new ArrayCollection();
        $this->delivery = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|DeliveryRound[]
     */
    public function getDeliveryRounds(): Collection
    {
        return $this->deliveryRounds;
    }

    public function addDeliveryRound(DeliveryRound $deliveryRound): self
    {
        if (!$this->deliveryRounds->contains($deliveryRound)) {
            $this->deliveryRounds[] = $deliveryRound;
            $deliveryRound->setStatus($this);
        }

        return $this;
    }

    public function removeDeliveryRound(DeliveryRound $deliveryRound): self
    {
        if ($this->deliveryRounds->removeElement($deliveryRound)) {
            // set the owning side to null (unless already changed)
            if ($deliveryRound->getStatus() === $this) {
                $deliveryRound->setStatus(null);
            }
        }

        return $this;
    }

    public function setDeliveryRounds(?array $deliveryRounds): self {
        foreach($this->getdeliveryRounds()->toArray() as $deliveryRound) {
            $this->removeDeliveryRound($deliveryRound);
        }

        $this->deliveryRounds = new ArrayCollection();
        foreach($deliveryRounds as $deliveryRound) {
            $this->addDeliveryRound($deliveryRound);
        }

        return $this;
    }

    /**
     * @return Collection|OrderStatusHistory[]
     */
    public function getOrderStatusHistory(): Collection
    {
        return $this->orderStatusHistory;
    }

    public function addOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if (!$this->orderStatusHistory->contains($orderStatusHistory)) {
            $this->orderStatusHistory[] = $orderStatusHistory;
            $orderStatusHistory->setStatus($this);
        }

        return $this;
    }

    public function removeOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if ($this->orderStatusHistory->removeElement($orderStatusHistory)) {
            if ($orderStatusHistory->getStatus() === $this) {
                $orderStatusHistory->setStatus(null);
            }
        }

        return $this;
    }

    public function setOrderStatusHistory(?array $orderStatusHistories): self {
        foreach($this->getOrderStatusHistory()->toArray() as $orderStatusHistory) {
            $this->removeOrderStatusHistory($orderStatusHistory);
        }

        $this->orderStatusHistory = new ArrayCollection();
        foreach($orderStatusHistories as $orderStatusHistory) {
            $this->addOrderStatusHistory($orderStatusHistory);
        }

        return $this;
    }

    /**
     * @return Collection|Order[]
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Order $order): self {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setStatus($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): self {
        if ($this->orders->removeElement($order)) {
            if ($order->getStatus() === $this) {
                $order->setStatus(null);
            }
        }

        return $this;
    }

    public function setOrders(?array $orders): self {
        foreach($this->getOrders()->toArray() as $order) {
            $this->removeOrder($order);
        }

        $this->orders = new ArrayCollection();
        foreach($orders as $order) {
            $this->addOrder($order);
        }

        return $this;
    }

    /**
     * @return Collection|Collect[]
     */
    public function getCollects(): Collection
    {
        return $this->collects;
    }

    public function addCollect(Collect $collect): self
    {
        if (!$this->collects->contains($collect)) {
            $this->collects[] = $collect;
            $collect->setStatus($this);
        }

        return $this;
    }

    public function removeCollect(Collect $collect): self
    {
        if ($this->collects->removeElement($collect)) {
            // set the owning side to null (unless already changed)
            if ($collect->getStatus() === $this) {
                $collect->setStatus(null);
            }
        }

        return $this;
    }

    public function setCollects(?array $collects): self {
        foreach($this->getCollects()->toArray() as $collect) {
            $this->removeCollect($collect);
        }

        $this->collects = new ArrayCollection();
        foreach($collects as $collect) {
            $this->addCollect($collect);
        }

        return $this;
    }

    /**
     * @return Collection|Delivery[]
     */
    public function getDelivery(): Collection
    {
        return $this->delivery;
    }

    public function addDelivery(Delivery $delivery): self {
        if (!$this->delivery->contains($delivery)) {
            $this->delivery[] = $delivery;
            $delivery->setStatus($this);
        }

        return $this;
    }

    public function removeDelivery(Delivery $delivery): self {
        if ($this->delivery->removeElement($delivery)) {
            if ($delivery->getStatus() === $this) {
                $delivery->setStatus(null);
            }
        }

        return $this;
    }

    public function setDeliveries(?array $deliveries): self {
        foreach($this->getDelivery()->toArray() as $delivery) {
            $this->removeDelivery($delivery);
        }

        $this->delivery = new ArrayCollection();
        foreach($deliveries as $delivery) {
            $this->addDelivery($delivery);
        }

        return $this;
    }
}
