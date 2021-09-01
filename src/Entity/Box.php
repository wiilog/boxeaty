<?php

namespace App\Entity;

use App\Repository\BoxRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoxRepository::class)
 */
class Box {

    public const OWNER_BOXEATY = "boxeaty";

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
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="box", cascade={"remove"}, orphanRemoval=true)
     */
    private Collection $boxRecords;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $canGenerateDepositTicket = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $uses = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $isBox = null;

    /**
     * @ORM\OneToMany(targetEntity=DepositTicket::class, mappedBy="box")
     */
    private Collection $depositTickets;

    /**
     * @ORM\ManyToMany(targetEntity=CounterOrder::class, mappedBy="boxes")
     */
    private Collection $counterOrders;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    private ?DateTime $creationDate;

    /**
     * @ORM\ManyToMany(targetEntity=Collect::class, mappedBy="crates")
     */
    private Collection $collects;

    /**
     * @ORM\OneToMany(targetEntity=Box::class, mappedBy="crate")
     */
    private Collection $containedBoxes;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="containedBoxes")
     */
    private ?Box $crate = null;

    /**
     * @ORM\OneToMany(targetEntity=PreparationLine::class, mappedBy="crate")
     */
    private Collection $cratePreparationLines;

    /**
     * @ORM\ManyToMany(targetEntity=PreparationLine::class, mappedBy="boxes")
     */
    private Collection $boxPreparationLines;

    /**
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="crate")
     */
    private Collection $cratePackingRecords;

    public function __construct() {
        $this->boxRecords = new ArrayCollection();
        $this->counterOrders = new ArrayCollection();
        $this->creationDate = new DateTime("now");
        $this->collects = new ArrayCollection();
        $this->containedBoxes = new ArrayCollection();
        $this->cratePreparationLines = new ArrayCollection();
        $this->boxPreparationLines = new ArrayCollection();
        $this->cratePackingRecords = new ArrayCollection();
    }

    public function fromRecord(BoxRecord $record): self {
        return $this->setState($record->getState())
            ->setLocation($record->getLocation())
            ->setQuality($record->getQuality())
            ->setOwner($record->getClient())
            ->setComment($record->getComment());
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
     * @return Collection|BoxRecord[]
     */
    public function getBoxRecords(): Collection {
        return $this->boxRecords;
    }

    public function addBoxRecord(BoxRecord $boxRecord): self {
        if (!$this->boxRecords->contains($boxRecord)) {
            $this->boxRecords[] = $boxRecord;

            if ($boxRecord->getBox() !== $this) {
                $boxRecord->setBox($this);
            }
        }

        return $this;
    }

    public function removeBoxRecord(BoxRecord $boxRecord): self {
        if ($this->boxRecords->removeElement($boxRecord)) {
            // set the owning side to null (unless already changed)
            if ($boxRecord->getBox() === $this) {
                $boxRecord->setBox(null);
            }
        }

        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        if ($this->getLocation()
            && $location !== $this->getLocation()) {
            $this->getLocation()->removeBox($this);
        }

        $this->location = $location;

        if ($this->location) {
            $this->location->addBox($this);
        }

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

    /**
     * @return Collection|DepositTicket[]
     */
    public function getDepositTickets(): Collection {
        return $this->depositTickets;
    }

    public function addDepositTicket(DepositTicket $depositTicket): self {
        if (!$this->depositTickets->contains($depositTicket)) {
            $this->depositTickets[] = $depositTicket;
            $depositTicket->setBox($this);
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self {
        if ($this->depositTickets->removeElement($depositTicket)) {
            if ($depositTicket->getBox() === $this) {
                $depositTicket->setBox(null);
            }
        }

        return $this;
    }

    public function setDepositTickets(?array $depositTickets): self {
        foreach ($this->getDepositTickets()->toArray() as $depositTicket) {
            $this->removeDepositTicket($depositTicket);
        }

        $this->depositTickets = new ArrayCollection();
        foreach ($depositTickets as $depositTicket) {
            $this->addDepositTicket($depositTicket);
        }

        return $this;
    }

    /**
     * @return Collection|CounterOrder[]
     */
    public function getCounterOrders(): Collection {
        return $this->counterOrders;
    }

    public function addCounterOrder(CounterOrder $order): self {
        if (!$this->counterOrders->contains($order)) {
            $this->counterOrders[] = $order;
            $order->addBox($this);
        }

        return $this;
    }

    public function removeCounterOrder(CounterOrder $order): self {
        if ($this->counterOrders->removeElement($order)) {
            $order->removeBox($this);
        }

        return $this;
    }


    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
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
            $collect->addCrate($this);
        }

        return $this;
    }

    public function removeCollect(Collect $collect): self {
        if ($this->collects->removeElement($collect)) {
            $collect->removeCrate($this);
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

    public function isBox(): ?bool {
        return $this->isBox;
    }

    public function setIsBox(?bool $isBox): self {
        $this->isBox = $isBox;

        return $this;
    }

    public function getCrate(): ?Box{
        return $this->crate;
    }

    public function setCrate(?Box $crate): self {
        if($this->crate && $this->crate !== $crate) {
            $this->crate->removeContainedBox($this);
        }
        $this->crate = $crate;
        if($crate) {
            $crate->addContainedBox($this);
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getContainedBoxes(): Collection {
        return $this->containedBoxes;
    }

    public function addContainedBox(Box $containedBox): self {
        if (!$this->containedBoxes->contains($containedBox)) {
            $this->containedBoxes[] = $containedBox;
            $containedBox->setCrate($this);
        }

        return $this;
    }

    public function removeContainedBox(Box $containedBox): self {
        if ($this->containedBoxes->removeElement($containedBox)) {
            if ($containedBox->getCrate() === $this) {
                $containedBox->setCrate(null);
            }
        }

        return $this;
    }

    public function setContainedBoxes(?array $containedBoxes): self {
        foreach($this->getContainedBoxes()->toArray() as $containedBox) {
            $this->removeContainedBox($containedBox);
        }

        $this->containedBoxes = new ArrayCollection();
        foreach($containedBoxes as $containedBox) {
            $this->addContainedBox($containedBox);
        }

        return $this;
    }

    /**
     * @return Collection|PreparationLine[]
     */
    public function getCratePreparationLines(): Collection {
        return $this->cratePreparationLines;
    }

    public function addCratePreparationLine(PreparationLine $line): self {
        if (!$this->cratePreparationLines->contains($line)) {
            $this->cratePreparationLines[] = $line;
            $line->setCrate($this);
        }

        return $this;
    }

    public function removeCratePreparationLine(PreparationLine $line): self {
        if ($this->cratePreparationLines->removeElement($line)) {
            if ($line->getCrate() === $this) {
                $line->setCrate(null);
            }
        }

        return $this;
    }

    public function setCratePreparationLines(?array $lines): self {
        foreach($this->getCratePreparationLines()->toArray() as $line) {
            $this->removeCratePreparationLine($line);
        }

        $this->cratePreparationLines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addCratePreparationLine($line);
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxPreparationLines(): Collection {
        return $this->boxPreparationLines;
    }

    public function addBoxPreparationLine(PreparationLine $line): self {
        if (!$this->boxPreparationLines->contains($line)) {
            $this->boxPreparationLines[] = $line;
            $line->addBox($this);
        }

        return $this;
    }

    public function removeBoxPreparationLine(PreparationLine $line): self {
        if ($this->boxPreparationLines->removeElement($line)) {
            $line->removeBox($this);
        }

        return $this;
    }

    public function setBoxPreparationLines(?array $lines): self {
        foreach($this->getBoxPreparationLines()->toArray() as $line) {
            $this->removeBoxPreparationLine($line);
        }

        $this->boxPreparationLines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addBoxPreparationLine($line);
        }

        return $this;
    }

    /**
     * @return Collection|BoxRecord[]
     */
    public function getCratePackingRecords(): Collection {
        return $this->cratePackingRecords;
    }

    public function addCratePackingRecord(BoxRecord $boxRecord): self {
        if (!$this->cratePackingRecords->contains($boxRecord)) {
            $this->cratePackingRecords[] = $boxRecord;
            $boxRecord->setCrate($this);
        }

        return $this;
    }

    public function removeCratePackingRecord(BoxRecord $boxRecord): self {
        if ($this->cratePackingRecords->removeElement($boxRecord)) {
            if ($boxRecord->getCrate() === $this) {
                $boxRecord->setCrate(null);
            }
        }

        return $this;
    }

    public function setCratePackingRecords(?array $boxRecords): self {
        foreach($this->getCratePackingRecords()->toArray() as $boxRecord) {
            $this->removeCratePackingRecord($boxRecord);
        }

        $this->cratePackingRecords = new ArrayCollection();
        foreach($boxRecords as $boxRecord) {
            $this->addCratePackingRecord($boxRecord);
        }

        return $this;
    }

}
