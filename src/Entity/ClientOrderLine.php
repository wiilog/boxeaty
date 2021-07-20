<?php

namespace App\Entity;

use App\Repository\ClientOrderLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientOrderLineRepository::class)
 */
class ClientOrderLine {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private ?int $quantity = null;

    /**
     * @ORM\ManyToOne (targetEntity=BoxType::class, inversedBy="clientOrderBoxType")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?BoxType $boxType = null;

    /**
     * @ORM\ManyToOne (targetEntity=ClientOrder::class, inversedBy="clientOrderBoxType")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $clientOrder = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getQuantity(): ?int {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self {
        $this->quantity = $quantity;

        return $this;
    }

    public function getBoxType(): ?BoxType {
        return $this->boxType;
    }

    public function setBoxType(?BoxType $boxType): self {
        if($this->boxType && $this->boxType !== $boxType) {
            $this->boxType->removeClientOrderLine($this);
        }
        $this->boxType = $boxType;
        if($boxType) {
            $boxType->addClientOrderLine($this);
        }

        return $this;
    }

    public function getClientOrder(): ?ClientOrder {
        return $this->clientOrder;
    }

    public function setClientOrder(?ClientOrder $clientOrder): self {
        if($this->clientOrder && $this->clientOrder !== $clientOrder) {
            $this->clientOrder->removeClientOrderLine($this);
        }
        $this->clientOrder = $clientOrder;
        if($clientOrder) {
            $clientOrder->addClientOrderLine($this);
        }

        return $this;
    }
}
