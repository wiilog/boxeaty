<?php

namespace App\Entity;

use App\Repository\PreparationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PreparationRepository::class)
 */
class Preparation
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\OneToOne(targetEntity=Depository::class, mappedBy="preparation")
     */
    private ?Depository $depository;

    /**
     * @ORM\OneToOne(targetEntity=Delivery::class, mappedBy="preparation")
     */
    private ?Delivery $delivery;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepository(): ?Depository
    {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if($this->depository && $this->depository->getPreparation() === $this) {
            $this->depository->setPreparation(null);
        }
        $this->depository = $depository;
        if($depository) {
            $depository->setPreparation($this);
        }

        return $this;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): self {
        if($this->delivery && $this->delivery->getPreparation() === $this) {
            $this->delivery->setPreparation(null);
        }
        $this->delivery = $delivery;
        if($delivery) {
            $delivery->setPreparation($this);
        }

        return $this;
    }
    
}
