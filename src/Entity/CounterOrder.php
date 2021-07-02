<?php

namespace App\Entity;

use App\Repository\CounterOrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CounterOrderRepository::class)
 */
class CounterOrder {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $date = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class)
     */
    private ?Client $client = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class)
     */
    private ?Location $location = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private ?User $user = null;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="orders")
     */
    private Collection $boxes;

    /**
     * @ORM\ManyToMany(targetEntity=DepositTicket::class, inversedBy="orders")
     */
    private Collection $depositTickets;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private ?string $boxPrice = null;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private ?string $depositTicketPrice = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $number;

    /**
     * @ORM\Column(type="date")
     */
    private $estimatedDelivery;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $deliveryMode;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $shouldCreateCollect;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $collectBoxNumber;

    /**
     * @ORM\Column(type="text")
     */
    private $comment;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $deliveryPrice;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $servicePrice;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class, inversedBy="orders")
     */
    private ?Status $status;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="order")
     */
    private ?User $requester;

    /**
     * @ORM\ManyToOne(targetEntity=OrderType::class, inversedBy="orders")
     */
    private ?OrderType $type;

    /**
     * @ORM\OneToOne(targetEntity=OrderStatusHistory::class, mappedBy="orderId", cascade={"persist", "remove"})
     */
    private ?OrderStatusHistory $orderStatusHistory;

    /**
     * @ORM\OneToOne(targetEntity=Collect::class, mappedBy="orderId", cascade={"persist", "remove"})
     */
    private ?Collect $collect;

    /**
     * @ORM\OneToOne(targetEntity=Delivery::class, mappedBy="orderId", cascade={"persist", "remove"})
     */
    private ?Delivery $delivery;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->depositTickets = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;
        return $this;
    }

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        $this->client = $client;
        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;
        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection {
        return $this->boxes;
    }

    public function addBox(Box $box): self {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->addOrder($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            $box->removeOrder($this);
        }

        return $this;
    }

    public function setBoxes(?array $boxes): self {
        foreach($this->getBoxes()->toArray() as $box) {
            $this->removeBox($box);
        }

        $this->boxes = new ArrayCollection();
        foreach($boxes as $box) {
            $this->addBox($box);
        }

        return $this;
    }


    /**
     * @return Collection|DepositTicket[]
     */
    public function getDepositTickets(): Collection {
        return $this->depositTickets;
    }

    public function addDepositTicket(DepositTicket $depositTicket): self {
        if (!$this->depositTickets->contains($depositTicket)) {
            $this->depositTickets[] = $depositTicket;
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self {
        $this->depositTickets->removeElement($depositTicket);

        return $this;
    }

    public function setDepositTickets(?array $depositTickets): self {
        foreach($this->getDepositTickets()->toArray() as $depositTicket) {
            $this->removeDepositTicket($depositTicket);
        }

        $this->depositTickets = new ArrayCollection();
        foreach($depositTickets as $depositTicket) {
            $this->addDepositTicket($depositTicket);
        }

        return $this;
    }

    public function getBoxPrice(): ?string {
        return $this->boxPrice;
    }

    public function setBoxPrice(?string $boxPrice): self {
        $this->boxPrice = $boxPrice;
        return $this;
    }

    public function getDepositTicketPrice(): ?string {
        return $this->depositTicketPrice;
    }

    public function setDepositTicketPrice(?string $depositTicketPrice): self {
        $this->depositTicketPrice = $depositTicketPrice;
        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getEstimatedDelivery(): ?\DateTimeInterface
    {
        return $this->estimatedDelivery;
    }

    public function setEstimatedDelivery(\DateTimeInterface $estimatedDelivery): self
    {
        $this->estimatedDelivery = $estimatedDelivery;

        return $this;
    }

    public function getDeliveryMode(): ?string
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(string $deliveryMode): self
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getShouldCreateCollect(): ?bool
    {
        return $this->shouldCreateCollect;
    }

    public function setShouldCreateCollect(bool $shouldCreateCollect): self
    {
        $this->shouldCreateCollect = $shouldCreateCollect;

        return $this;
    }

    public function getCollectBoxNumber(): ?int
    {
        return $this->collectBoxNumber;
    }

    public function setCollectBoxNumber(int $collectBoxNumber): self
    {
        $this->collectBoxNumber = $collectBoxNumber;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDeliveryPrice(): ?float
    {
        return $this->deliveryPrice;
    }

    public function setDeliveryPrice(float $deliveryPrice): self
    {
        $this->deliveryPrice = $deliveryPrice;

        return $this;
    }

    public function getServicePrice(): ?float
    {
        return $this->servicePrice;
    }

    public function setServicePrice(float $servicePrice): self
    {
        $this->servicePrice = $servicePrice;

        return $this;
    }


    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(Status $status): self
    {
        if($this->status && $this->status !== $status) {
            $this->status->removeOrder($this);
        }
        $this->status = $status;
        if($status) {
            $status->addOrder($this);
        }

        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): self {
        if($this->requester && $this->requester !== $requester) {
            $this->requester->removeOrder($this);
        }
        $this->requester = $requester;
        if($requester) {
            $requester->addOrder($this);
        }

        return $this;
    }

    public function getType(): ?OrderType
    {
        return $this->type;
    }

    public function setType(?OrderType $type): self {
        if($this->type && $this->type !== $type) {
            $this->type->removeOrder($this);
        }
        $this->type = $type;
        if($type) {
            $type->addOrder($this);
        }

        return $this;
    }

    public function getOrderStatusHistory(): ?OrderStatusHistory
    {
        return $this->orderStatusHistory;
    }

    public function setOrderStatusHistory(?OrderStatusHistory $orderStatusHistory): self
    {
        if($this->orderStatusHistory && $this->orderStatusHistory->getOrderId() === $this) {
            $this->orderStatusHistory->setOrderId(null);
        }
        $this->orderStatusHistory = $orderStatusHistory;
        if($orderStatusHistory) {
            $orderStatusHistory->setOrderId($this);
        }

        return $this;
    }

    public function getCollect(): ?Collect
    {
        return $this->collect;
    }

    public function setCollect(?Collect $collect): self
    {
        if($this->collect && $this->collect->getOrderId() === $this) {
            $this->collect->setOrderId(null);
        }
        $this->collect = $collect;
        if($collect) {
            $collect->setOrderId($this);
        }

        return $this;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): self
    {
        if($this->delivery && $this->delivery->getOrderId() === $this) {
            $this->delivery->setOrderId(null);
        }
        $this->delivery = $delivery;
        if($delivery) {
            $delivery->setOrderId($this);
        }

        return $this;
    }

}
