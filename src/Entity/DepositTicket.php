<?php

namespace App\Entity;

use App\Repository\DepositTicketRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DepositTicketRepository::class)
 */
class DepositTicket {

    public const VALID = 1;
    public const SPENT = 2;
    public const EXPIRED = 3;

    public const NAMES = [
        self::VALID => "Valide",
        self::SPENT => "Utilisé",
        self::EXPIRED => "Expiré",
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="depositTickets")
     */
    private ?Box $box = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $validityDate = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $number = null;

    /**
     * @ORM\Column(type="integer", length=255)
     */
    private ?int $state = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $useDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="orderDepositTickets")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?User $orderUser = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="depositTickets")
     */
    private ?Location $location = null;

    /**
     * @ORM\ManyToMany(targetEntity=CounterOrder::class, mappedBy="depositTickets")
     */
    private Collection $counterOrders;

    /**
     * @ORM\Column(nullable=true)
     */
    private ?string $consumerEmail;

    public function __construct()
    {
        $this->counterOrders = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getBox(): ?Box {
        return $this->box;
    }

    public function setBox(?Box $box): self {
        $this->box = $box;
        return $this;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getValidityDate(): ?DateTime {
        return $this->validityDate;
    }

    public function setValidityDate(DateTime $validityDate): self {
        $this->validityDate = $validityDate;

        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getState(): ?int {
        return $this->state;
    }

    public function setState(int $state): self {
        $this->state = $state;

        return $this;
    }

    public function getUseDate(): ?DateTime {
        return $this->useDate;
    }

    public function setUseDate(DateTime $useDate): self {
        $this->useDate = $useDate;

        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;

        return $this;
    }

    /**
     * @return Collection|CounterOrder[]
     */
    public function getCounterOrders(): Collection
    {
        return $this->counterOrders;
    }

    public function addCounterOrder(CounterOrder $order): self
    {
        if (!$this->counterOrders->contains($order)) {
            $this->counterOrders[] = $order;
            $order->addDepositTicket($this);
        }

        return $this;
    }

    public function removeCounterOrder(CounterOrder $order): self
    {
        if ($this->counterOrders->removeElement($order)) {
            $order->removeDepositTicket($this);
        }

        return $this;
    }

    public function getConsumerEmail(): ?string
    {
        return $this->consumerEmail;
    }

    public function setConsumerEmail(?string $consumerEmail): self
    {
        $this->consumerEmail = $consumerEmail;

        return $this;
    }

    public function setOrderUser(?User $orderUser): self {
        if ($this->getOrderUser()
            && $this->getOrderUser() !== $orderUser) {
            $this->getOrderUser()->removeOrderDepositTicket($this);
        }
        $this->orderUser = $orderUser;
        if ($this->orderUser) {
            $this->orderUser->addOrderDepositTicket($this);
        }
        return $this;
    }

    public function getOrderUser(): ?User {
        return $this->orderUser;
    }

}
