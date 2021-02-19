<?php

namespace App\Entity;

use App\Repository\DepositTicketRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DepositTicketRepository::class)
 */
class DepositTicket
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @ORM\Column(type="datetime")
     */
    private $validityDate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $number;

    /**
     * @ORM\Column(type="string", length=255, name="`condition`", nullable=false)
     */
    private $condition;

    /**
     * @ORM\Column(type="datetime")
     */
    private $useDate;

    /**
     * @ORM\ManyToOne(targetEntity=Kiosk::class, inversedBy="depositTickets")
     */
    private $kiosk;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getValidityDate(): ?\DateTimeInterface
    {
        return $this->validityDate;
    }

    public function setValidityDate(\DateTimeInterface $validityDate): self
    {
        $this->validityDate = $validityDate;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(string $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    public function getUseDate(): ?\DateTimeInterface
    {
        return $this->useDate;
    }

    public function setUseDate(\DateTimeInterface $useDate): self
    {
        $this->useDate = $useDate;

        return $this;
    }

    public function getKiosk(): ?Kiosk
    {
        return $this->kiosk;
    }

    public function setKiosk(?Kiosk $kiosk): self
    {
        $this->kiosk = $kiosk;

        return $this;
    }
}
