<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LocationRepository::class)
 */
class Location {

    public const DELIVERER = "Livreur";

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $kiosk = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true, nullable=true)
     */
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="kiosks")
     */
    private ?Client $client = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $deposits = null;

    /**
     * @ORM\OneToMany(targetEntity=Box::class, mappedBy="location")
     */
    private Collection $boxes;

    /**
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="location")
     */
    private Collection $trackingMovements;

    /**
     * @ORM\OneToMany(targetEntity=DepositTicket::class, mappedBy="location")
     */
    private Collection $depositTickets;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function isKiosk(): ?bool {
        return $this->kiosk;
    }

    public function setKiosk(?bool $kiosk): self {
        $this->kiosk = $kiosk;
        return $this;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(string $code): self {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        $this->client = $client;

        return $this;
    }

    public function getDeposits(): ?int {
        return $this->deposits;
    }

    public function setDeposits(?int $deposits): self {
        $this->deposits = $deposits;
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
            $box->setLocation($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            // set the owning side to null (unless already changed)
            if ($box->getLocation() === $this) {
                $box->setLocation(null);
            }
        }

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
            $trackingMovement->setLocation($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if ($this->trackingMovements->removeElement($trackingMovement)) {
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getLocation() === $this) {
                $trackingMovement->setLocation(null);
            }
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
            $depositTicket->setLocation($this);
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self {
        if ($this->depositTickets->removeElement($depositTicket)) {
            // set the owning side to null (unless already changed)
            if ($depositTicket->getLocation() === $this) {
                $depositTicket->setLocation(null);
            }
        }

        return $this;
    }

}
