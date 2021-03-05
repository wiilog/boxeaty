<?php

namespace App\Entity;

use App\Repository\BoxRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoxRepository::class)
 */
class Box {

    public const AVAILABLE = 1;
    public const UNAVAILABLE = 2;
    public const CONSUMER = 3;
    public const CLIENT = 4;
    public const OUT = 5;

    public const NAMES = [
        self::AVAILABLE => "Disponible",
        self::UNAVAILABLE => "Indisponible",
        self::CONSUMER => "Consommateur",
        self::CLIENT => "Client",
        self::OUT => "Sorti",
    ];

    public const LINKED_COLORS = [
        self::AVAILABLE => "success",
        self::UNAVAILABLE => "danger",
        self::CONSUMER => "primary",
        self::CLIENT => "warning",
        self::OUT => "secondary",
    ];

    public const DEFAULT_COLOR = 'light';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $number = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="boxes")
     */
    private ?Location $location = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $state = null;

    /**
     * @ORM\ManyToOne(targetEntity=Quality::class, inversedBy="boxes")
     */
    private ?Quality $quality = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="boxes")
     */
    private ?Client $owner = null;

    /**
     * @ORM\ManyToOne(targetEntity=BoxType::class, inversedBy="boxes")
     */
    private ?BoxType $type = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="box", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private Collection $trackingMovements;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $canGenerateDepositTicket = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $uses = null;

    /**
     * @ORM\ManyToMany(targetEntity=Order::class, mappedBy="boxes")
     */
    private $orders;

    public function __construct() {
        $this->trackingMovements = new ArrayCollection();
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

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;

            if ($trackingMovement->getBox() !== $this) {
                $trackingMovement->setBox($this);
            }
        }

        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;

        return $this;
    }

    public function getState(): ?int {
        return $this->state;
    }

    public function setState(?int $state): self {
        $this->state = $state;

        return $this;
    }

    public function getQuality(): ?Quality {
        return $this->quality;
    }

    public function setQuality(?Quality $quality): self {
        $this->quality = $quality;

        return $this;
    }

    public function getOwner(): ?Client {
        return $this->owner;
    }

    public function setOwner(?Client $owner): self {
        $this->owner = $owner;

        return $this;
    }

    public function getType(): ?BoxType {
        return $this->type;
    }

    public function setType(?BoxType $type): self {
        $this->type = $type;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getCanGenerateDepositTicket(): ?bool {
        return $this->canGenerateDepositTicket;
    }

    public function setCanGenerateDepositTicket(?bool $canGenerateDepositTicket): self {
        $this->canGenerateDepositTicket = $canGenerateDepositTicket;
        return $this;
    }

    public function getUses(): ?int {
        return $this->uses;
    }

    public function setUses(?int $uses): self {
        $this->uses = $uses;
        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if ($this->trackingMovements->removeElement($trackingMovement)) {
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getBox() === $this) {
                $trackingMovement->setBox(null);
            }
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

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->addBox($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            $order->removeBox($this);
        }

        return $this;
    }

    public function fromTrackingMovement(TrackingMovement $movement): self {
        return $this->setState($movement->getState())
            ->setLocation($movement->getLocation())
            ->setQuality($movement->getQuality())
            ->setOwner($movement->getClient())
            ->setComment($movement->getComment());
    }

}
