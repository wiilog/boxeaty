<?php

namespace App\Entity;

use WiiCommon\Helper\Stream;
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
     * @ORM\OneToMany(targetEntity=Collect::class, mappedBy="location")
     */
    private Collection $collects;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->boxRecords = new ArrayCollection();
        $this->collects = new ArrayCollection();
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
    public function getCollects(): Collection {
        return $this->collects;
    }

    public function addCollect(Collect $collect): self {
        if (!$this->collects->contains($collect)) {
            $this->collects[] = $collect;
            $collect->setLocation($this);
        }

        return $this;
    }

    public function removeCollect(Collect $collect): self {
        if ($this->collects->removeElement($collect)) {
            // set the owning side to null (unless already changed)
            if ($collect->getLocation() === $this) {
                $collect->setLocation(null);
            }
        }

        return $this;
    }

    public function setCollect(?array $collects): self {
        foreach ($this->getCollects()->toArray() as $collect) {
            $this->removeCollect($collect);
        }

        $this->collects = new ArrayCollection();
        foreach ($collects as $collect) {
            $this->addCollect($collect);
        }

        return $this;
    }

}
