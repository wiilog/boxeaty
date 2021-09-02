<?php

namespace App\Entity;

use App\Repository\BoxRecordRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BoxRecordRepository::class)
 */
class BoxRecord {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $date = null;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="cratePackingRecords")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Box $crate = null;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="boxRecords")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Box $box = null;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class, inversedBy="boxRecords")
     */
    private ?Location $location = null;

    /**
     * @ORM\ManyToOne(targetEntity=Quality::class, inversedBy="records")
     */
    private ?Quality $quality = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $state = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="boxRecords")
     */
    private ?Client $client = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="boxRecords")
     */
    private ?User $user = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private ?bool $trackingMovement;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDate(): ?DateTime {
        return $this->date;
    }

    public function setDate(DateTime $date): self {
        $this->date = $date;

        return $this;
    }

    public function getBox(): ?Box {
        return $this->box;
    }

    public function setBox(?Box $box): self {
        if($this->box && $this->box !== $box) {
            $this->box->removeBoxRecord($this);
        }
        $this->box = $box;
        if($box) {
            $box->addBoxRecord($this);
        }

        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;

        return $this;
    }

    public function getQuality(): ?Quality {
        return $this->quality;
    }

    public function setQuality(?Quality $quality): self {
        $this->quality = $quality;

        return $this;
    }

    public function getState(): ?int {
        return $this->state;
    }

    public function setState(?int $state): self {
        $this->state = $state;

        return $this;
    }

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        $this->client = $client;

        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;

        return $this;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function isTrackingMovement(): ?bool {
        return $this->trackingMovement;
    }

    public function setTrackingMovement(bool $trackingMovement): self {
        $this->trackingMovement = $trackingMovement;
        return $this;
    }

    public function getCrate(): ?Box {
        return $this->crate;
    }

    public function setCrate(?Box $crate): self {
        if($this->crate && $this->crate !== $crate) {
            $this->crate->removeCratePackingRecord($this);
        }
        $this->crate = $crate;
        if($crate) {
            $crate->addCratePackingRecord($this);
        }

        return $this;
    }

    public function copyBox(?Box $from = null): self {
        $box = $from ?? $this->box ?? null;
        if ($box) {
            $this->setCrate($box->getCrate())
                ->setLocation($box->getLocation())
                ->setClient($box->getOwner())
                ->setQuality($box->getQuality())
                ->setState($box->getState())
                ->setComment($box->getComment());
        }

        return $this;
    }

}
