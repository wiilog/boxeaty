<?php

namespace App\Entity;

use App\Repository\OrderRecurrenceRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderRecurrenceRepository::class)
 */
class OrderRecurrence {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $monthlyPrice = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $period = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $crateAmount = null;

    /**
     * @ORM\Column(type="date")
     */
    private ?DateTime $start = null;

    /**
     * @ORM\Column(type="date")
     */
    private ?DateTime $end = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $deliveryFlatRate = null;

    /**
     * @ORM\Column(type="decimal", precision=8, scale=2)
     */
    private ?string $serviceFlatRate = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $day = null;

    /**
     * @ORM\Column(type="date")
     */
    private ?DateTime $lastEdit = null;

    public function getId(): ?int {
        return $this->id;
    }

    /**
     * @return float
     */
    public function getMonthlyPrice() {
        return $this->monthlyPrice;
    }

    /**
     * @param float $monthlyPrice
     * @return self
     */
    public function setMonthlyPrice($monthlyPrice): self {
        $this->monthlyPrice = $monthlyPrice;
        return $this;
    }

    public function getPeriod(): ?int {
        return $this->period;
    }

    public function setPeriod(int $period): self {
        $this->period = $period;

        return $this;
    }

    public function getCrateAmount(): ?int {
        return $this->crateAmount;
    }

    public function setCrateAmount(int $crateAmount): self {
        $this->crateAmount = $crateAmount;

        return $this;
    }

    public function getStart(): ?DateTime {
        return $this->start;
    }

    public function setStart(DateTime $start): self {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?DateTime {
        return $this->end;
    }

    public function setEnd(DateTime $end): self {
        $this->end = $end;

        return $this;
    }

    public function getDeliveryFlatRate(): ?float {
        return $this->deliveryFlatRate;
    }

    public function setDeliveryFlatRate(float $deliveryFlatRate): self {
        $this->deliveryFlatRate = $deliveryFlatRate;

        return $this;
    }

    public function getServiceFlatRate(): ?float {
        return $this->serviceFlatRate;
    }

    public function setServiceFlatRate(float $serviceFlatRate): self {
        $this->serviceFlatRate = $serviceFlatRate;

        return $this;
    }

    public function getDay(): ?int {
        return $this->day;
    }

    public function setDay(int $day): self {
        $this->day = $day;

        return $this;
    }

    public function getLastEdit(): ?DateTime {
        return $this->lastEdit;
    }

    public function setLastEdit(?DateTime $lastEdit): self {
        $this->lastEdit = $lastEdit;
        return $this;
    }

}
