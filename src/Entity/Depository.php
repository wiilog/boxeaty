<?php

namespace App\Entity;

use App\Repository\DepositoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DepositoryRepository::class)
 */
class Depository {

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="depositories")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Client $client = null;

    /**
     * @ORM\Column(type="string")
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\OneToMany(targetEntity=Preparation::class, mappedBy="depository")
     */
    private Collection $preparations;

    /**
     * @ORM\OneToMany(targetEntity=DeliveryRound::class, mappedBy="depository")
     */
    private Collection $deliveryRounds;

    /**
     * @ORM\OneToMany(targetEntity=Location::class, mappedBy="depot")
     */
    private Collection $locations;

    public function __construct() {
        $this->preparations = new ArrayCollection();
        $this->deliveryRounds = new ArrayCollection();
        $this->locations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        if ($this->client && $this->client !== $client) {
            $this->client->removeDepository($this);
        }
        $this->client = $client;
        if ($client) {
            $client->addDepository($this);
        }

        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function setDescription(?string $description): self {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Preparation[]
     */
    public function getPreparations(): Collection {
        return $this->preparations;
    }

    public function addPreparation(Preparation $preparation): self {
        if (!$this->preparations->contains($preparation)) {
            $this->preparations[] = $preparation;
            $preparation->setDepository($this);
        }

        return $this;
    }

    public function removePreparation(Preparation $preparation): self {
        if ($this->preparations->removeElement($preparation)) {
            if ($preparation->getDepository() === $this) {
                $preparation->setDepository(null);
            }
        }

        return $this;
    }

    public function setPreparations(?array $preparations): self {
        foreach ($this->getPreparations()->toArray() as $preparation) {
            $this->removePreparation($preparation);
        }

        $this->preparations = new ArrayCollection();
        foreach ($preparations as $preparation) {
            $this->addPreparation($preparation);
        }

        return $this;
    }

    /**
     * @return Collection|DeliveryRound[]
     */
    public function getDeliveryRounds(): Collection {
        return $this->deliveryRounds;
    }

    public function addDeliveryRound(DeliveryRound $deliveryRound): self {
        if (!$this->deliveryRounds->contains($deliveryRound)) {
            $this->deliveryRounds[] = $deliveryRound;
            $deliveryRound->setDepository($this);
        }

        return $this;
    }

    public function removeDeliveryRound(DeliveryRound $deliveryRound): self {
        if ($this->deliveryRounds->removeElement($deliveryRound)) {
            if ($deliveryRound->getDepository() === $this) {
                $deliveryRound->setDepository(null);
            }
        }

        return $this;
    }

    public function setDeliveryRounds(?array $deliveryRounds): self {
        foreach ($this->getDeliveryRounds()->toArray() as $deliveryRound) {
            $this->removeDeliveryRound($deliveryRound);
        }

        $this->deliveryRounds = new ArrayCollection();
        foreach ($deliveryRounds as $deliveryRound) {
            $this->addDeliveryRound($deliveryRound);
        }

        return $this;
    }

    public function getLocation(): Collection
    {
        return $this->locations;
    }

    public function addLocation(Location $location): self {
        if (!$this->locations->contains($location)) {
            $this->locations[] = $location;
            $location->setDepot($this);
        }

        return $this;
    }

    public function removeLocation(Location $location): self {
        if ($this->locations->removeElement($location)) {
            if ($location->getDepot() === $this) {
                $location->setDepot(null);
            }
        }

        return $this;
    }

    public function setLocations(?array $locations): self
    {
        foreach($this->getLocation()->toArray() as $location) {
            $this->removeLocation($location);
        }

        $this->locations = new ArrayCollection();
        foreach($locations as $location) {
            $this->addLocation($location);
        }

        return $this;
    }

}
