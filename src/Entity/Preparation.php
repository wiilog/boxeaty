<?php

namespace App\Entity;

use App\Repository\PreparationRepository;
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
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrder::class, inversedBy="preparation")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $order;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="preparations")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Depository $depository;

    public function getId(): ?int {
        return $this->id;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): self {
        $this->status = $status;
        return $this;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getPreparation() === $this) {
            $this->order->setPreparation(null);
        }
        $this->order = $order;
        if ($order) {
            $order->setPreparation($this);
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
