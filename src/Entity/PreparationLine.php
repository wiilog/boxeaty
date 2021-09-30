<?php

namespace App\Entity;

use App\Repository\PreparationLineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PreparationLineRepository::class)
 */
class PreparationLine {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Preparation::class, inversedBy="lines")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Preparation $preparation = null;

    /**
     * @ORM\ManyToOne(targetEntity=Box::class, inversedBy="cratePreparationLines")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Box $crate = null;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="boxPreparationLines")
     */
    private ?Collection $boxes;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private bool $taken = false;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     */
    private bool $deposited = false;

    public function __construct() {
        $this->boxes = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getPreparation(): ?Preparation {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self {
        if($this->preparation && $this->preparation !== $preparation) {
            $this->preparation->removeLine($this);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->addLine($this);
        }

        return $this;
    }

    public function getCrate(): ?Box {
        return $this->crate;
    }

    public function setCrate(?Box $crate): self {
        if($this->crate && $this->crate !== $crate) {
            $this->crate->removeCratePreparationLine($this);
        }
        $this->crate = $crate;
        if($crate) {
            $crate->addCratePreparationLine($this);
        }

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection {
        return $this->boxes;
    }

    public function addBox(Box $box): self {
        if(!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->addBoxPreparationLine($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if($this->boxes->removeElement($box)) {
            $box->removeBoxPreparationLine($this);
        }

        return $this;
    }

    public function setBoxes(?array $boxes): self {
        foreach($this->getBoxes()->toArray() as $box) {
            $this->removeBox($box);
        }

        $this->boxes = new ArrayCollection();
        foreach($boxes as $box) {
            $this->addBox($box);
        }

        return $this;
    }

    public function isTaken(): bool {
        return $this->taken;
    }

    public function setTaken(bool $taken): self {
        $this->taken = $taken;
        return $this;
    }

    public function isDeposited(): bool {
        return $this->deposited;
    }

    public function setDeposited(bool $deposited): self {
        $this->deposited = $deposited;
        return $this;
    }

}
