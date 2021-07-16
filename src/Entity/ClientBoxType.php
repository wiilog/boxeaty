<?php

namespace App\Entity;

use App\Repository\CrateTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private $id;

    /**
     * @ORM\Column(type="float")
     */
    private $cost;

    /**
     * @ORM\Column(type="integer")
     */
    private $quantity;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="clientBoxTypes")
     */
    private $client;

    /**
     * @ORM\ManyToOne(targetEntity=BoxType::class, inversedBy="clientBoxTypes")
     */
    private $boxType;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function setCost(float $cost): self
    {
        $this->cost = $cost;

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

    public function setBoxType(?BoxType $boxType): self
    {
        $this->boxType = $boxType;

        return $this;
    }
}
