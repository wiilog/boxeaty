<?php

namespace App\Entity;

use App\Entity\Utils\StatusTrait;
use App\Repository\PreparationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PreparationRepository::class)
 */
class Preparation {

    use StatusTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity=ClientOrder::class, inversedBy="preparation")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?ClientOrder $order = null;

    /**
     * @ORM\ManyToOne(targetEntity=Depository::class, inversedBy="preparations")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Depository $depository = null;

    /**
     * @ORM\OneToMany(targetEntity=PreparationLine::class, mappedBy="preparation", cascade={"remove"})
     */
    private ?Collection $lines;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="preparations")
     */
    private ?User $operator = null;

    public function __construct() {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrder(): ?ClientOrder {
        return $this->order;
    }

    public function setOrder(?ClientOrder $order): self {
        if ($this->order && $this->order->getPreparation() !== $this) {
            $oldPreparation = $this->order;
            $this->order = null;
            $oldPreparation->setPreparation(null);
        }
        $this->order = $order;
        if ($this->order && $this->order->getPreparation() !== $this) {
            $this->order->setPreparation($this);
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

    public function getOperator(): ?User {
        return $this->operator;
    }

    public function setOperator(?User $operator): self {
        if($this->operator && $this->operator !== $operator) {
            $this->operator->removePreparation($this);
        }

        $this->operator = $operator;

        if($operator) {
            $operator->addPreparation($this);
        }

        return $this;
    }

}
