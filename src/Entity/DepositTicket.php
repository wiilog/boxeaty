<?php

namespace App\Entity;

use App\Repository\DepositTicketRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DepositTicketRepository::class)
 */
class DepositTicket {

    public const VALID = 1;
    public const SPENT = 2;
    public const EXPIRED = 3;

    public const NAMES = [
        self::VALID => "Valide",
        self::SPENT => "Utilisé",
        self::EXPIRED => "Expiré",
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class)
     */
    private ?Box $box = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $validityDate = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $number = null;

    /**
     * @ORM\Column(type="integer", length=255)
     */
    private ?int $state = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $useDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="depositTickets")
     */
    private ?Location $location = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getBox(): ?Box {
        return $this->box;
    }

    public function setBox(?Box $box): self {
        $this->box = $box;
        return $this;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getValidityDate(): ?DateTime {
        return $this->validityDate;
    }

    public function setValidityDate(DateTime $validityDate): self {
        $this->validityDate = $validityDate;

        return $this;
    }

    public function getNumber(): ?string {
        return $this->number;
    }

    public function setNumber(string $number): self {
        $this->number = $number;

        return $this;
    }

    public function getState(): ?int {
        return $this->state;
    }

    public function setState(int $state): self {
        $this->state = $state;

        return $this;
    }

    public function getUseDate(): ?DateTime {
        return $this->useDate;
    }

    public function setUseDate(DateTime $useDate): self {
        $this->useDate = $useDate;

        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;

        return $this;
    }

}
