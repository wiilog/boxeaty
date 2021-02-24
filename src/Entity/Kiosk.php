<?php

namespace App\Entity;

use App\Repository\KioskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=KioskRepository::class)
 */
class Kiosk
{
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
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="kiosks")
     */
    private ?Client $client = null;

    /**
     * @ORM\OneToMany(targetEntity=DepositTicket::class, mappedBy="kiosk")
     */
    private Collection $depositTickets;

    public function __construct()
    {
        $this->depositTickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return Collection|DepositTicket[]
     */
    public function getDepositTickets(): Collection
    {
        return $this->depositTickets;
    }

    public function addDepositTicket(DepositTicket $depositTicket): self
    {
        if (!$this->depositTickets->contains($depositTicket)) {
            $this->depositTickets[] = $depositTicket;
            $depositTicket->setKiosk($this);
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self
    {
        if ($this->depositTickets->removeElement($depositTicket)) {
            // set the owning side to null (unless already changed)
            if ($depositTicket->getKiosk() === $this) {
                $depositTicket->setKiosk(null);
            }
        }

        return $this;
    }

}
