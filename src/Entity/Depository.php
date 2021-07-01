<?php

namespace App\Entity;

use App\Repository\DepositoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DepositoryRepository::class)
 */
class Depository
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $active;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToOne(targetEntity=Preparation::class, inversedBy="depository")
     */
    private ?Preparation $preparation;

    /**
     * @ORM\OneToOne(targetEntity=DeliveryRound::class, inversedBy="depositoriy")
     */
    private ?DeliveryRound $deliveryRound;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="depositories")
     */
    private ?Client $client;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getName(): ?int
    {
        return $this->name;
    }

    public function setName(int $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function setPreparation(?Preparation $preparation): self
    {
        if($this->preparation && $this->preparation->getDepository() === $this) {
            $this->preparation->setDepository(null);
        }
        $this->preparation = $preparation;
        if($preparation) {
            $preparation->setDepository($this);
        }

        return $this;
    
    }

    public function getDeliveryRound(): ?DeliveryRound
    {
        return $this->deliveryRound;
    }

    public function setDeliveryRound(?DeliveryRound $deliveryRound): self
    {
        if($this->deliveryRound && $this->deliveryRound->getDepository() === $this) {
            $this->deliveryRound->setDepository(null);
        }
        $this->deliveryRound = $deliveryRound;
        if($deliveryRound) {
            $deliveryRound->setDepository($this);
        }

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        if($this->client && $this->client !== $client) {
            $this->client->removeDepository($this);
        }
        $this->client = $client;
        if($client) {
            $client->addDepository($this);
        }

        return $this;
    }
}
