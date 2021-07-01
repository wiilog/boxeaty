<?php

namespace App\Entity;

use App\Repository\OrderTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderTypeRepository::class)
 */
class OrderType
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
     * @ORM\ManyToMany(targetEntity=Client::class, mappedBy="orderType")
     */
    private Collection $clients;

    /**
     * @ORM\OneToMany (targetEntity=Order::class, inversedBy="type")
     */
    private ?Order $order;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
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
     * @return Collection|Client[]
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->addOrderType($this);
        }

        return $this;
    }

    public function removeClient(Client $client): self
    {
        if ($this->clients->removeElement($client)) {
            $client->removeOrderType($this);
        }

        return $this;
    }

    public function setClients(?array $clients): self {
        foreach($this->getClients()->toArray() as $client) {
            $this->removeClient($client);
        }

        $this->clients = new ArrayCollection();
        foreach($clients as $client) {
            $this->addClient($client);
        }

        return $this;
    }

    public function getOrders(): ?Order
    {
        return $this->orders;
    }

    public function addOrder(?Order $order): self {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setType($this);
        }

        return $this;
    }

    public function removeOrder(?Order $order): self {
        if ($this->orders->removeElement($order)) {
            if ($order->getType() === $this) {
                $order->setType(null);
            }
        }

        return $this;
    }

    public function setOrders(?Order $orders): self
    {
        foreach($this->getOrders()->toArray() as $order) {
            $this->removeOrder($order);
        }

        $this->orders = new ArrayCollection();
        foreach($orders as $order) {
            $this->addOrder($order);
        }

        return $this;
    }
}
