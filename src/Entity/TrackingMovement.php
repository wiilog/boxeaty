<?php

namespace App\Entity;

use App\Repository\TrackingMovementRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=TrackingMovementRepository::class)
 */
class TrackingMovement {

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
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="trackingMovements")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Box $box = null;

    /**
     * @ORM\ManyToOne(targetEntity=Quality::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Quality $quality = null;

    /**
     * @ORM\ManyToOne(targetEntity=State::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?State $state = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Client $client = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $comment = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="trackingMovements")
     */
    private $operator;

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
        $previous = $this->getBox();
        if ($previous) {
            $previous->removeTrackingMovement($this);
        }

        $this->box = $box;
        $box->addTrackingMovement($this);

        return $this;
    }

    public function getQuality(): ?Quality {
        return $this->quality;
    }

    public function setQuality(?Quality $quality): self {
        $this->quality = $quality;

        return $this;
    }

    public function getState(): ?State {
        return $this->state;
    }

    public function setState(?State $state): self {
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

    public function getComment(): ?string {
        return $this->comment;
    }

    public function setComment(?string $comment): self {
        $this->comment = $comment;

        return $this;
    }

    public function getOperator(): ?User
    {
        return $this->operator;
    }

    public function setOperator(?User $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

}
