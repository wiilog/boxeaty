<?php

namespace App\Entity;

use App\Repository\OrderRecurrenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderRecurrenceRepository::class)
 */
class OrderRecurrence
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $frequency;

    /**
     * @ORM\Column(type="integer")
     */
    private $crateAmount;

    /**
     * @ORM\Column(type="date")
     */
    private $start;

    /**
     * @ORM\Column(type="date")
     */
    private $end;

    /**
     * @ORM\Column(type="float")
     */
    private $deliveryFlatRate;

    /**
     * @ORM\Column(type="float")
     */
    private $serviceFlatRate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFrequency(): ?int
    {
        return $this->frequency;
    }

    public function setFrequency(int $frequency): self
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getCrateAmount(): ?int
    {
        return $this->crateAmount;
    }

    public function setCrateAmount(int $crateAmount): self
    {
        $this->crateAmount = $crateAmount;

        return $this;
    }

    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(\DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(\DateTimeInterface $end): self
    {
        $this->end = $end;

        return $this;
    }

    public function getDeliveryFlatRate(): ?float
    {
        return $this->deliveryFlatRate;
    }

    public function setDeliveryFlatRate(float $deliveryFlatRate): self
    {
        $this->deliveryFlatRate = $deliveryFlatRate;

        return $this;
    }

    public function getServiceFlatRate(): ?float
    {
        return $this->serviceFlatRate;
    }

    public function setServiceFlatRate(float $serviceFlatRate): self
    {
        $this->serviceFlatRate = $serviceFlatRate;

        return $this;
    }
}
