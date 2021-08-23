<?php

namespace App\Entity;

use App\Entity\Utils\StatusTrait;
use App\Repository\ClientOrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WiiCommon\Helper\Stream;

/**
 * @ORM\Entity(repositoryClass=ClientOrderRepository::class)
 */
class ClientOrder {

    use StatusTrait;

    public const PREFIX_NUMBER = 'CO';

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
     * @ORM\OneToMany(targetEntity=OrderStatusHistory::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Collection $orderStatusHistory;

    /**
     * @ORM\OneToMany(targetEntity=ClientOrderLine::class, mappedBy="clientOrder")
     */
    private ?Collection $lines;

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
    private ?DateTime $expectedDelivery = null;

    /**
     * @ORM\ManyToOne(targetEntity=DeliveryMethod::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?DeliveryMethod $deliveryMethod = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $automatic = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $cratesAmount = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $tokensAmount = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?float $deliveryPrice = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?float $servicePrice = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private ?User $validator = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $validatedAt = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="clientOrders")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $requester = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity=DeliveryRound::class, inversedBy="orders")
     */
    private ?DeliveryRound $deliveryRound = null;

    /**
     * @ORM\OneToOne(targetEntity=Preparation::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Preparation $preparation = null;

    /**
     * @ORM\OneToOne(targetEntity=Delivery::class, mappedBy="order", cascade={"persist", "remove"})
     */
    private ?Delivery $delivery = null;

    /**
     * @ORM\Column(type="integer", nullable="true")
     */
    private ?int $cratesAmountToCollect = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $collectRequired = false;

    /**
     * @ORM\OneToOne(targetEntity=Collect::class, mappedBy="clientOrder", cascade={"persist", "remove"})
     */
    private ?Collect $collect = null;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="clientOrders")
     */
    private ?Depository $depository = null;

    public function __construct(){
        $this->lines = new ArrayCollection();
        $this->orderStatusHistory = new ArrayCollection();
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

    public function getType(): ?OrderType {
        return $this->type;
    }

    public function setType(?OrderType $type): self {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection|OrderStatusHistory[]
     */
    public function getOrderStatusHistory(): Collection {
        return $this->orderStatusHistory;
    }

    /**
     * @return OrderStatusHistory[]
     */
    public function getEditableStatusHistory(): array {
        $previousStatus = null;
        $statuses = [];

        $history = array_reverse($this->getOrderStatusHistory()->toArray());
        foreach ($history as $status) {
            $hierarchy = array_search($status->getStatus()->getCode(), Status::ORDER_STATUS_HIERARCHY);

            if ($previousStatus !== null && $hierarchy > $previousStatus) {
                break;
            }

            $statuses[] = $status;
            $previousStatus = $hierarchy;
        }

        return Stream::from($statuses)
            ->slice(1)
            ->reverse()
            ->toArray();
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
        foreach ($this->getOrderStatusHistory()->toArray() as $item) {
            $this->removeOrderStatusHistory($item);
        }

        $this->orderStatusHistory = new ArrayCollection();
        foreach ($orderStatusHistory as $item) {
            $this->addOrderStatusHistory($item);
        }

        return $this;
    }

    public function getExpectedDelivery(): ?DateTime {
        return $this->expectedDelivery;
    }

    public function setExpectedDelivery(DateTime $expectedDelivery): self {
        $this->expectedDelivery = $expectedDelivery;
        return $this;
    }

    public function getDeliveryMethod(): ?DeliveryMethod {
        return $this->deliveryMethod;
    }

    public function setDeliveryMethod(?DeliveryMethod $deliveryMethod): self {
        $this->deliveryMethod = $deliveryMethod;
        return $this;
    }

    public function getAutomatic(): ?bool {
        return $this->automatic;
    }

    public function setAutomatic(bool $automatic): self {
        $this->automatic = $automatic;

        return $this;
    }

    public function getCratesAmount(): ?int {
        return $this->cratesAmount;
    }

    public function setCratesAmount(int $cratesAmount): self {
        $this->cratesAmount = $cratesAmount;

        return $this;
    }

    public function getTokensAmount(): ?int {
        return $this->tokensAmount;
    }

    public function setTokensAmount(int $tokensAmount): self {
        $this->tokensAmount = $tokensAmount;

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

    public function getValidator(): ?User {
        return $this->validator;
    }

    public function setValidator(?User $validator): self {
        $this->validator = $validator;
        return $this;
    }

    public function getValidatedAt(): ?DateTime {
        return $this->validatedAt;
    }

    public function setValidatedAt(?DateTime $validatedAt): self {
        $this->validatedAt = $validatedAt;
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

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        if ($this->preparation && $this->preparation->getOrder() === $this) {
            $this->preparation->setOrder(null);
        }
        $this->preparation = $preparation;
        if ($preparation) {
            $preparation->setOrder($this);
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

    public function getDeliveryRound(): ?DeliveryRound {
        return $this->deliveryRound;
    }

    public function setDeliveryRound(?DeliveryRound $deliveryRound): self {
        if ($this->deliveryRound && $this->deliveryRound !== $deliveryRound) {
            $this->deliveryRound->removeOrder($this);
        }
        $this->deliveryRound = $deliveryRound;
        if ($deliveryRound) {
            $deliveryRound->addOrder($this);
        }

        return $this;
    }

    /**
     * @return Collection|ClientOrderLine[]
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(ClientOrderLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setClientOrder($this);
        }

        return $this;
    }

    public function removeLine(ClientOrderLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getClientOrder() === $this) {
                $line->setClientOrder(null);
            }
        }
        return $this;
    }

    public function setLines(?array $lines): self {
        foreach($this->getLines()->toArray() as $clientOrderLine) {
            $this->removeLine($clientOrderLine);
        }

        $this->lines = new ArrayCollection();

        foreach($lines as $clientOrderLine) {
            $this->addLine($clientOrderLine);
        }

        return $this;
    }

    public function getCratesAmountToCollect(): ?int {
        return $this->cratesAmountToCollect;
    }

    public function setCratesAmountToCollect(?int $cratesAmountToCollect): self {
        $this->cratesAmountToCollect = $cratesAmountToCollect;
        return $this;
    }

    public function isCollectRequired(): bool {
        return $this->collectRequired;
    }

    public function setCollectRequired(bool $collectRequired): self {
        $this->collectRequired = $collectRequired;
        return $this;
    }

    public function getTotalAmount(): float {
        return Stream::from($this->lines)
            ->reduce(
                fn(int $total, ClientOrderLine $line) => $total + ($line->getQuantity() * ($line->getBoxType()->getPrice() ?: 0)),
                0
            );
    }

    public function getBoxQuantity(): float {
        return Stream::from($this->lines)
            ->reduce(
                fn(int $total, ClientOrderLine $line) => $total + $line->getQuantity(),
                0
            );
    }

    public function getTotalWeight(): float {
        return Stream::from($this->lines)
            ->reduce(
                fn(int $total, ClientOrderLine $line) => $total + ($line->getQuantity() * ($line->getBoxType()->getWeight() ?: 0)),
                0
            );
    }

    public function getTotalVolume(): float {
        return Stream::from($this->lines)
            ->reduce(
                fn(int $total, ClientOrderLine $line) => $total + ($line->getQuantity() * ($line->getBoxType()->getVolume() ?: 0)),
                0
            );
    }

    public function getCollect(): ?Collect {
        return $this->collect;
    }

    public function setCollect(?Collect $collect): self {
        if ($this->collect && $this->collect->getClientOrder() === $this) {
            $this->collect->setClientOrder(null);
        }
        $this->collect = $collect;
        if ($collect) {
            $collect->setClientOrder($this);
        }

        return $this;
    }

    public function getDepository(): ?Depository {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if($this->depository && $this->depository !== $depository) {
            $this->depository->removeClientOrder($this);
        }
        $this->depository = $depository;
        if($depository) {
            $depository->addClientOrder($this);
        }

        return $this;
    }

    public function getClientClosedPark(): ?Client {
        $client = $this->getClient();
        $clientOrderInformation = $client ? $client->getClientOrderInformation() : null;
        return ($clientOrderInformation && $clientOrderInformation->isClosedParkOrder())
            ? $client
            : null;
    }
}
