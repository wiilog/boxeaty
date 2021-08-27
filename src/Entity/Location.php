<?php

namespace App\Entity;

use WiiCommon\Helper\Stream;
use App\Entity\Utils\ActiveTrait;
use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=LocationRepository::class)
 */
class Location {

    use ActiveTrait;

    public const MIN_KIOSK_CAPACITY = 1;

    const RECEPTION = "Reception";
    const EXPEDITION = "Expedition";
    const STOCK = "Stock";
    const QUALITY = "Qualite";

    const LOCATION_TYPES = [
        1 => self::RECEPTION,
        2 => self::EXPEDITION,
        3 => self::STOCK,
        4 => self::QUALITY
    ];

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
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="locations")
     */
    private ?Client $client = null;

    /**
     * @ORM\OneToOne(targetEntity=Client::class, mappedBy="outLocation")
     */
    private ?Client $outClient = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $capacity = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $message = null;

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
     * @ORM\OneToMany(targetEntity=Collect::class, mappedBy="pickLocation")
     */
    private Collection $pickedCollects;

    /**
     * @ORM\OneToMany(targetEntity=Collect::class, mappedBy="dropLocation")
     */
    private Collection $droppedCollects;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $type = null;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="locations")
     */
    private ?Depository $depository = null;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->boxRecords = new ArrayCollection();
        $this->pickedCollects = new ArrayCollection();
        $this->droppedCollects = new ArrayCollection();
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

    public function getOutClient(): ?Client {
        return $this->outClient;
    }

    public function setOutClient(?Client $outClient): self {
        $this->outClient = $outClient;
        return $this;
    }

    public function getCapacity(): ?int {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): self {
        $this->capacity = $capacity;

        return $this;
    }

    public function getMessage(): ?string {
        return $this->message;
    }

    public function setMessage(?string $message): self {
        $this->message = $message;

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

    public function serialize(): array {
        return [
            "id" => $this->getId(),
            "name" => $this->getName(),
            "capacity" => $this->getCapacity(),
            "client" => null,
            "boxes" => Stream::from($this->getBoxes())
                ->map(fn(Box $box) => [
                    "id" => $box->getId(),
                    "number" => $box->getNumber(),
                ])
                ->toArray()
        ];
    }

    /**
     * @return Collection|Collect[]
     */
    public function getPickedCollects(): Collection {
        return $this->pickedCollects;
    }

    public function addPickedCollect(Collect $pickedCollect): self {
        if (!$this->pickedCollects->contains($pickedCollect)) {
            $this->pickedCollects[] = $pickedCollect;
            $pickedCollect->setPickLocation($this);
        }

        return $this;
    }

    public function removePickedCollect(Collect $pickedCollects): self {
        if ($this->pickedCollects->removeElement($pickedCollects)) {
            // set the owning side to null (unless already changed)
            if ($pickedCollects->getPickLocation() === $this) {
                $pickedCollects->setPickLocation(null);
            }
        }

        return $this;
    }

    public function setPickedCollect(?array $pickedCollects): self {
        foreach ($this->getPickedCollects()->toArray() as $pickedCollect) {
            $this->removePickedCollect($pickedCollect);
        }

        $this->pickedCollects = new ArrayCollection();
        foreach ($pickedCollects as $pickedCollect) {
            $this->addPickedCollect($pickedCollect);
        }

        return $this;
    }

    /**
     * @return Collection|Collect[]
     */
    public function getDroppedCollects(): Collection {
        return $this->droppedCollects;
    }

    public function addDroppedCollect(Collect $droppedCollect): self {
        if (!$this->droppedCollects->contains($droppedCollect)) {
            $this->droppedCollects[] = $droppedCollect;
            $droppedCollect->setDropLocation($this);
        }

        return $this;
    }

    public function removeDroppedCollect(Collect $droppedCollect): self {
        if ($this->droppedCollects->removeElement($droppedCollect)) {
            // set the owning side to null (unless already changed)
            if ($droppedCollect->getDropLocation() === $this) {
                $droppedCollect->setDropLocation(null);
            }
        }

        return $this;
    }

    public function setDroppedCollects(?array $droppedCollects): self {
        foreach ($this->getDroppedCollects()->toArray() as $droppedCollect) {
            $this->removeDroppedCollect($droppedCollect);
        }

        $this->droppedCollects = new ArrayCollection();
        foreach ($droppedCollects as $droppedCollect) {
            $this->addDroppedCollect($droppedCollect);
        }

        return $this;
    }


    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDepository(): ?Depository
    {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if($this->depository && $this->depository !== $depository) {
            $this->depository->removeLocation($this);
        }
        $this->depository = $depository;
        if($depository) {
            $depository->addLocation($this);
        }

        return $this;
    }


}
