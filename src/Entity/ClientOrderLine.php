<?php

namespace App\Entity;

use App\Repository\ClientOrderLineRepository;
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
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?float $unitPrice = null;

    /**
     * @ORM\ManyToOne(targetEntity=BoxType::class, inversedBy="clientOrderLines")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?BoxType $boxType = null;

    /**
     * @ORM\ManyToOne (targetEntity=ClientOrder::class, inversedBy="lines")
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
            $this->clientOrder->removeLine($this);
        }
        $this->clientOrder = $clientOrder;
        if($clientOrder) {
            $clientOrder->addLine($this);
        }

        return $this;
    }

    public function getUnitPrice(): ?float {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self {
        $this->unitPrice = $unitPrice;

        return $this;
    }

}
