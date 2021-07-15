<?php

namespace App\Entity;

use App\Repository\ClientOrderInformationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientOrderInformationRepository::class)
 */
class ClientOrderInformation
{
    public const BUY = 0;
    public const MANAGE = 1;
    public const BENEFIT = 2;

    public const ORDER_TYPES = [
        self::BUY => 'Achat / Négoce',
        self::MANAGE => 'Gestion autonome',
        self::BENEFIT => 'Prestation ponctuelle',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $depositoryDistance;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $tokenAmount;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $orderType;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isClosedParkOrder;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $workingDayDeliveryRate;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $nonWorkingDayDeliveryRate;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $serviceCost;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\ManyToOne(targetEntity=DeliveryMethod::class, inversedBy="clientOrderInformation")
     */
    private $deliveryMethod;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="clientOrderInformation")
     */
    private $depository;

    /**
     * @ORM\OneToOne(targetEntity=Client::class, cascade={"persist", "remove"})
     */
    private $client;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $paymentModes = [];

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

    public function getOrderType(): ?int
    {
        return $this->orderType;
    }

    public function setOrderType(?int $orderType): self
    {
        $this->orderType = $orderType;

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

    public function getDepository(): ?Depository
    {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self
    {
        $this->depository = $depository;

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

    public function getPaymentModes(): ?array
    {
        return $this->paymentModes;
    }

    public function setPaymentModes(?array $paymentModes): self
    {
        $this->paymentModes = $paymentModes;

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
