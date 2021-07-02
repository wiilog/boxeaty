<?php

namespace App\Entity;

use App\Repository\ClientOrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientOrderRepository::class)
 */
class ClientOrder {

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
     * @ORM\ManyToOne(targetEntity=OrderType::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?OrderType $type = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status = null;

    /**
     * @ORM\OneToMany(targetEntity=OrderStatusHistory::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Collection $orderStatusHistory;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class)
     */
    private ?Client $client = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $createdAt = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $estimatedDelivery = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $deliveryMode = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $shouldCreateCollect = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $collectBoxNumber = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?float $deliveryPrice = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?float $servicePrice = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="clientOrders")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $requester = null;

    /**
     * @ORM\OneToOne(targetEntity=Collect::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Collect $collect = null;

    /**
     * @ORM\OneToOne(targetEntity=Delivery::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Delivery $delivery = null;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="clientOrders")
     */
    private Collection $boxes;

    public function __construct() {
        $this->orderStatusHistory = new ArrayCollection();
        $this->boxes = new ArrayCollection();
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

    public function getCreatedAt(): ?DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        $this->client = $client;
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
            $box->addClientOrder($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            $box->addClientOrder($this);
        }

        return $this;
    }

    public function setBoxes(?array $boxes): self {
        foreach ($this->getBoxes()->toArray() as $box) {
            $this->removeBox($box);
        }

        $this->boxes = new ArrayCollection();
        foreach ($boxes as $box) {
            $this->addBox($box);
        }

        return $this;
    }

    public function getType(): ?OrderType {
        return $this->type;
    }

    public function setType(?OrderType $type): self {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(Status $status): self {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection|OrderStatusHistory[]
     */
    public function getOrderStatusHistory(): Collection {
        return $this->orderStatusHistory;
    }

    public function addOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if (!$this->orderStatusHistory->contains($orderStatusHistory)) {
            $this->orderStatusHistory[] = $orderStatusHistory;
            $orderStatusHistory->setOrder($this);
        }

        return $this;
    }

    public function removeOrderStatusHistory(OrderStatusHistory $orderStatusHistory): self {
        if ($this->orderStatusHistory->removeElement($orderStatusHistory)) {
            if ($orderStatusHistory->getOrder() === $this) {
                $orderStatusHistory->setOrder(null);
            }
        }

        return $this;
    }

    public function setOrderStatusHistory(?array $orderStatusHistory): self {
        foreach($this->getOrderStatusHistory()->toArray() as $item) {
            $this->removeOrderStatusHistory($item);
        }

        $this->orderStatusHistory = new ArrayCollection();
        foreach($orderStatusHistory as $item) {
            $this->addOrderStatusHistory($item);
        }

        return $this;
    }

    public function getEstimatedDelivery(): ?DateTime {
        return $this->estimatedDelivery;
    }

    public function setEstimatedDelivery(DateTime $estimatedDelivery): self {
        $this->estimatedDelivery = $estimatedDelivery;
        return $this;
    }

    public function getDeliveryMode(): ?string {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(string $deliveryMode): self {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getShouldCreateCollect(): ?bool {
        return $this->shouldCreateCollect;
    }

    public function setShouldCreateCollect(bool $shouldCreateCollect): self {
        $this->shouldCreateCollect = $shouldCreateCollect;

        return $this;
    }

    public function getCollectBoxNumber(): ?int {
        return $this->collectBoxNumber;
    }

    public function setCollectBoxNumber(int $collectBoxNumber): self {
        $this->collectBoxNumber = $collectBoxNumber;

        return $this;
    }

    public function getDeliveryPrice(): ?float {
        return $this->deliveryPrice;
    }

    public function setDeliveryPrice(float $deliveryPrice): self {
        $this->deliveryPrice = $deliveryPrice;

        return $this;
    }

    public function getServicePrice(): ?float {
        return $this->servicePrice;
    }

    public function setServicePrice(float $servicePrice): self {
        $this->servicePrice = $servicePrice;

        return $this;
    }

    public function getRequester(): ?User {
        return $this->requester;
    }

    public function setRequester(?User $requester): self {
        if ($this->requester && $this->requester !== $requester) {
            $this->requester->removeClientOrder($this);
        }
        $this->requester = $requester;
        if ($requester) {
            $requester->addClientOrder($this);
        }

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getCollect(): ?Collect {
        return $this->collect;
    }

    public function setCollect(?Collect $collect): self {
        if ($this->collect && $this->collect->getOrder() === $this) {
            $this->collect->setOrder(null);
        }
        $this->collect = $collect;
        if ($collect) {
            $collect->setOrder($this);
        }

        return $this;
    }

    public function getDelivery(): ?Delivery {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): self {
        if ($this->delivery && $this->delivery->getOrder() === $this) {
            $this->delivery->setOrder(null);
        }
        $this->delivery = $delivery;
        if ($delivery) {
            $delivery->setOrder($this);
        }

        return $this;
    }

}
