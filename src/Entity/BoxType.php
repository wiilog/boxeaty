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
    private $clientBoxTypes;

    /**
     * @ORM\Column(type="float")
     */
    private $volume;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $weight;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $image;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->clientBoxTypes = new ArrayCollection();
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

    public function setVolume(float $volume): self
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

}
