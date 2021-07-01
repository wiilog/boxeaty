<?php

namespace App\Entity;

use App\Repository\DeliveryRoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryRoundRepository::class)
 */
class DeliveryRound
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $deliveryMode;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $price;

    /**
     * @ORM\Column(type="float")
     */
    private ?float $distance;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="deliveryRounds")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $deliverer;

    /**
     * @ORM\OneToOne(targetEntity=Depository::class, mappedBy="deliveryRound")
     */
    private Collection $depository;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class, inversedBy="deliveryRounds")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status;

    public function __construct()
    {
        $this->depositories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getDeliveryMode(): ?string
    {
        return $this->deliveryMode;
    }

    public function setDeliveryMode(string $deliveryMode): self
    {
        $this->deliveryMode = $deliveryMode;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDeliverer(): ?User
    {
        return $this->deliverer;
    }

    public function setDeliverer(?User $deliverer): self
    {
        if($this->deliverer && $this->deliverer !== $deliverer) {
            $this->deliverer->removeDeliveryRound($this);
        }
        $this->deliverer = $deliverer;
        if($deliverer) {
            $deliverer->addDeliveryRound($this);
        }

        return $this;
    }

    /**
     * @return Collection|Depository[]
     */
    public function getDepository(): Collection
    {
        return $this->depositories;
    }
    
    public function setDepository(?Depository $depository): self{
        if($this->depository && $this->depository->getDeliveryRound() === $this) {
            $this->depository->setDeliveryRound(null);
        }
        $this->depository = $depository;
        if($depository) {
            $depository->setDeliveryRound($this);
        }

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): self
    {
        if($this->status && $this->status !== $status) {
            $this->status->removeDeliveryRound($this);
        }
        $this->status = $status;
        if($status) {
            $status->addDeliveryRound($this);
        }

        return $this;
    }
}
