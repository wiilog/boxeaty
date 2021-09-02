<?php

namespace App\Entity;

use App\Repository\CratePatternLineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CratePatternLineRepository::class)
 */
class CratePatternLine
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private ?float $customUnitPrice = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $quantity = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="cratePatternLines")
     */
    private ?Client $client = null;

    /**
     * @ORM\ManyToOne(targetEntity=BoxType::class, inversedBy="cratePatternLines")
     */
    private ?BoxType $boxType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomUnitPrice(): ?float
    {
        return $this->customUnitPrice;
    }

    public function setCustomUnitPrice(?float $customUnitPrice): self
    {
        $this->customUnitPrice = $customUnitPrice;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        if($this->client && $this->client !== $client) {
            $this->client->removeCratePatternLine($this);
        }
        $this->client = $client;
        if($client) {
            $client->addCratePatternLine($this);
        }

        return $this;
    }

    public function getBoxType(): ?BoxType
    {
        return $this->boxType;
    }

    public function setBoxType(?BoxType $boxType): self {
        $this->boxType = $boxType;
        return $this;
    }

    public function getUnitPrice(): ?float {
        return $this->customUnitPrice
            ?: ($this->boxType ? $this->boxType->getPrice() : null)
            ?: null;
    }
}
