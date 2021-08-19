<?php

namespace App\Entity;

use App\Repository\PreparationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PreparationRepository::class)
 */
class Preparation {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrder::class, inversedBy="preparation")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $order;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="preparations")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Depository $depository;

    /**
     * @ORM\OneToMany(targetEntity=PreparationLine::class, mappedBy="preparation", cascade={"remove"})
     */
    private Collection $lines;

    public function __construct() {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): self {
        $this->status = $status;
        return $this;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getPreparation() === $this) {
            $this->order->setPreparation(null);
        }
        $this->order = $order;
        if ($order) {
            $order->setPreparation($this);
        }

        return $this;
    }

    public function getDepository(): ?Depository {
        return $this->depository;
    }

    public function setDepository(?Depository $depository): self {
        if ($this->depository && $this->depository !== $depository) {
            $this->depository->removePreparation($this);
        }
        $this->depository = $depository;
        if ($depository) {
            $depository->addPreparation($this);
        }

        return $this;
    }

    /**
     * @return Collection|PreparationLine[]
     */
    public function getLines(): Collection {
        return $this->lines;
    }

    public function addLine(PreparationLine $line): self {
        if (!$this->lines->contains($line)) {
            $this->lines[] = $line;
            $line->setPreparation($this);
        }

        return $this;
    }

    public function removeLine(PreparationLine $line): self {
        if ($this->lines->removeElement($line)) {
            if ($line->getPreparation() === $this) {
                $line->setPreparation(null);
            }
        }

        return $this;
    }

    public function setLines(?array $lines): self {
        foreach($this->getLines()->toArray() as $line) {
            $this->removeLine($line);
        }

        $this->lines = new ArrayCollection();
        foreach($lines as $line) {
            $this->addLine($line);
        }

        return $this;
    }

}
