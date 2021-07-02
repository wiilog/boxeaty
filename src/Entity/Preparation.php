<?php

namespace App\Entity;

use App\Repository\PreparationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PreparationRepository::class)
 */
class Preparation {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity=Delivery::class, mappedBy="preparation")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Delivery $delivery;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="preparations")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Depository $depository;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDelivery(): ?Delivery {
        return $this->delivery;
    }

    public function setDelivery(?Delivery $delivery): self {
        if ($this->delivery && $this->delivery->getPreparation() === $this) {
            $this->delivery->setPreparation(null);
        }
        $this->delivery = $delivery;
        if ($delivery) {
            $delivery->setPreparation($this);
        }

        return $this;
    }

    public function getDepository(): ?Depository {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if ($this->depository && $this->depository !== $depository) {
            $this->depository->removePreparation($this);
        }
        $this->depository = $depository;
        if ($depository) {
            $depository->addPreparation($this);
        }

        return $this;
    }

}
