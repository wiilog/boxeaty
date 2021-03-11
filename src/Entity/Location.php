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

    use Active;

    public const MIN_KIOSK_CAPACITY = 1;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class)
     */
    private ?Location $deporte = null;

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
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="location")
     */
    private Collection $boxRecords;

    /**
     * @ORM\OneToMany(targetEntity=DepositTicket::class, mappedBy="location")
     */
    private Collection $depositTickets;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $capacity = null;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->boxRecords = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDeporte(): ?Location {
        return $this->deporte;
    }

    public function setDeporte(?Location $deporte): self {
        $this->deporte = $deporte;
        return $this;
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
     * @return Collection|BoxRecord[]
     */
    public function getBoxRecords(): Collection {
        return $this->boxRecords;
    }

    public function addBoxRecord(BoxRecord $boxRecord): self {
        if (!$this->boxRecords->contains($boxRecord)) {
            $this->boxRecords[] = $boxRecord;
            $boxRecord->setLocation($this);
        }

        return $this;
    }

    public function removeBoxRecord(BoxRecord $boxRecord): self {
        if ($this->boxRecords->removeElement($boxRecord)) {
            // set the owning side to null (unless already changed)
            if ($boxRecord->getLocation() === $this) {
                $boxRecord->setLocation(null);
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

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }

}
