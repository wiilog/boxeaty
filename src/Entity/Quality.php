<?php

namespace App\Entity;

use App\Entity\Utils\ActiveTrait;
use App\Repository\QualityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=QualityRepository::class)
 */
class Quality {

    use ActiveTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\OneToMany(targetEntity=Box::class, mappedBy="quality")
     */
    private $boxes;

    /**
     * @ORM\OneToMany(targetEntity=BoxRecord::class, mappedBy="quality")
     */
    private $records;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $clean = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $broken = null;

    public function __construct() {
        $this->boxes = new ArrayCollection();
    }

    /**
     * @return bool|null
     */
    public function getBroken(): ?bool
    {
        return $this->broken;
    }

    /**
     * @param bool|null $broken
     * @return self
     */
    public function setBroken(?bool $broken): self
    {
        $this->broken = $broken;
        return $this;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection {
        return $this->boxes;
    }

    public function addBox(Box $box): self {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
            $box->setQuality($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            // set the owning side to null (unless already changed)
            if ($box->getQuality() === $this) {
                $box->setQuality(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|BoxRecord[]
     */
    public function getRecords(): Collection {
        return $this->records;
    }

    public function addRecord(BoxRecord $record): self {
        if (!$this->records->contains($record)) {
            $this->records[] = $record;
            $record->setQuality($this);
        }

        return $this;
    }

    public function removeRecord(BoxRecord $record): self {
        if ($this->records->removeElement($record)) {
            // set the owning side to null (unless already changed)
            if ($record->getQuality() === $this) {
                $record->setQuality(null);
            }
        }

        return $this;
    }

    public function isClean(): ?bool
    {
        return $this->clean;
    }

    public function setClean(bool $clean): self
    {
        $this->clean = $clean;

        return $this;
    }

}
