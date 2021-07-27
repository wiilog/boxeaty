<?php

namespace App\Entity;

use App\Repository\CrateTypeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CrateTypeRepository::class)
 */
class ClientBoxType
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
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="clientBoxTypes")
     */
    private ?Client $client = null;

    /**
     * @ORM\ManyToOne(targetEntity=BoxType::class, inversedBy="clientBoxTypes")
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

    public function setCustomUnitPrice(float $customUnitPrice): self
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

    public function setClient(?Client $client): self
    {
        $this->client = $client;

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
