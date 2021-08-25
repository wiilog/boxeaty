<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class ClientOrderInformation {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $depositoryDistance;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $tokenAmount;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $orderTypes = [];

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $isClosedParkOrder;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $workingDayDeliveryRate;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $nonWorkingDayDeliveryRate;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $serviceCost;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment;

    /**
     * @ORM\ManyToOne(targetEntity=DeliveryMethod::class, inversedBy="clientOrderInformation")
     */
    private ?DeliveryMethod $deliveryMethod;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="clientOrderInformation")
     */
    private ?Depository $depository = null;

    /**
     * @ORM\OneToOne(targetEntity=Client::class, mappedBy="clientOrderInformation", cascade={"persist", "remove"})
     */
    private $client;

    /**
     * @ORM\OneToOne(targetEntity=OrderRecurrence::class, cascade={"persist", "remove"})
     */
    private $orderRecurrence;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepositoryDistance(): ?int
    {
        return $this->depositoryDistance;
    }

    public function setDepositoryDistance(?int $depositoryDistance): self
    {
        $this->depositoryDistance = $depositoryDistance;

        return $this;
    }

    public function getTokenAmount(): ?int
    {
        return $this->tokenAmount;
    }

    public function setTokenAmount(?int $tokenAmount): self
    {
        $this->tokenAmount = $tokenAmount;

        return $this;
    }

    public function getOrderTypes(): ?array
    {
        return $this->orderTypes;
    }

    public function setOrderTypes(?array $orderTypes): self
    {
        if(!$orderTypes || $orderTypes[0] == null) {
            $this->orderTypes = [];
        } else {
            $this->orderTypes = $orderTypes;
        }

        return $this;
    }

    public function isClosedParkOrder(): ?bool
    {
        return $this->isClosedParkOrder;
    }

    public function setIsClosedParkOrder(?bool $isClosedParkOrder): self
    {
        $this->isClosedParkOrder = $isClosedParkOrder;

        return $this;
    }

    public function getWorkingDayDeliveryRate(): ?float
    {
        return $this->workingDayDeliveryRate;
    }

    public function setWorkingDayDeliveryRate(?float $workingDayDeliveryRate): self
    {
        $this->workingDayDeliveryRate = $workingDayDeliveryRate;

        return $this;
    }

    public function getNonWorkingDayDeliveryRate(): ?float
    {
        return $this->nonWorkingDayDeliveryRate;
    }

    public function setNonWorkingDayDeliveryRate(?float $nonWorkingDayDeliveryRate): self
    {
        $this->nonWorkingDayDeliveryRate = $nonWorkingDayDeliveryRate;

        return $this;
    }

    public function getServiceCost(): ?float
    {
        return $this->serviceCost;
    }

    public function setServiceCost(?float $serviceCost): self
    {
        $this->serviceCost = $serviceCost;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDeliveryMethod(): ?DeliveryMethod
    {
        return $this->deliveryMethod;
    }

    public function setDeliveryMethod(?DeliveryMethod $deliveryMethod): self
    {
        $this->deliveryMethod = $deliveryMethod;

        return $this;
    }

    public function getDepository(): ?Depository {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if($this->depository && $this->depository !== $depository) {
            $this->depository->removeClientOrderInformation($this);
        }
        $this->depository = $depository;
        if($depository) {
            $depository->addClientOrderInformation($this);
        }

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

    public function getOrderRecurrence(): ?OrderRecurrence
    {
        return $this->orderRecurrence;
    }

    public function setOrderRecurrence(?OrderRecurrence $orderRecurrence): self
    {
        $this->orderRecurrence = $orderRecurrence;

        return $this;
    }
}
