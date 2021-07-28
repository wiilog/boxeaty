<?php

namespace App\Entity;

use App\Repository\BoxTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoxTypeRepository::class)
 */
class BoxType {

    public const DEFAULT_VOLUME = 0.0005;

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private ?string $price = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $capacity = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $shape = null;

    /**
     * @ORM\OneToMany(targetEntity=Box::class, mappedBy="type")
     */
    private Collection $boxes;

    /**
     * @ORM\OneToMany(targetEntity=ClientBoxType::class, mappedBy="boxType")
     */
    private Collection $clientBoxTypes;

    /**
     * @ORM\OneToMany(targetEntity=ClientOrderLine::class, mappedBy="boxType", cascade={"persist", "remove"})
     */
    private Collection $clientOrderLines;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=4, nullable=true)
     */
    private ?float $volume = null;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2, nullable=true)
     */
    private ?float $weight = null;

    /**
     * @ORM\ManyToOne(targetEntity=Attachment::class)
     */
    private ?Attachment $image = null;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->clientBoxTypes = new ArrayCollection();
        $this->clientOrderLines = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?string {
        return $this->price;
    }

    public function setPrice(string $price): self {
        $this->price = $price;

        return $this;
    }

    public function getCapacity(): ?string {
        return $this->capacity;
    }

    public function setCapacity(?string $capacity): self {
        $this->capacity = $capacity;
        return $this;
    }

    public function getShape(): ?string {
        return $this->shape;
    }

    public function setShape(?string $shape): self {
        $this->shape = $shape;
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
            $box->setType($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            // set the owning side to null (unless already changed)
            if ($box->getType() === $this) {
                $box->setType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ClientBoxType[]
     */
    public function getClientBoxTypes(): Collection
    {
        return $this->clientBoxTypes;
    }

    public function addClientBoxType(ClientBoxType $clientBoxType): self
    {
        if (!$this->clientBoxTypes->contains($clientBoxType)) {
            $this->clientBoxTypes[] = $clientBoxType;
            $clientBoxType->setBoxType($this);
        }

        return $this;
    }

    public function removeClientBoxType(ClientBoxType $clientBoxType): self
    {
        if ($this->clientBoxTypes->removeElement($clientBoxType)) {
            // set the owning side to null (unless already changed)
            if ($clientBoxType->getBoxType() === $this) {
                $clientBoxType->setBoxType(null);
            }
        }

        return $this;
    }

    public function getVolume(): ?float
    {
        return $this->volume;
    }

    public function setVolume(?float $volume): self
    {
        $this->volume = $volume;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function getImage(): ?Attachment {
        return $this->image;
    }

    public function setImage(?Attachment $image): self {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection|ClientOrderLine[]
     */
    public function getClientOrderLines(): Collection {
        return $this->clientOrderLines;
    }

    public function addClientOrderLine(ClientOrderLine $clientOrderLine): self {
        if (!$this->clientOrderLines->contains($clientOrderLine)) {
            $this->clientOrderLines[] = $clientOrderLine;
            $clientOrderLine->setBoxType($this);
        }

        return $this;
    }

    public function removeClientOrderLine(ClientOrderLine $clientOrderLine): self {
        if ($this->clientOrderLines->removeElement($clientOrderLine)) {
            if ($clientOrderLine->getBoxType() === $this) {
                $clientOrderLine->setBoxType(null);
            }
        }
        return $this;
    }

    public function setClientOrderLines(?array $clientOrderLines): self {
        foreach($this->getClientOrderLines()->toArray() as $clientOrderLine) {
            $this->removeClientOrderLine($clientOrderLine);
        }

        $this->clientOrderLines = new ArrayCollection();
        foreach($clientOrderLines as $clientOrderLine) {
            $this->addClientOrderLine($clientOrderLine);
        }
        return $this;
    }
}
