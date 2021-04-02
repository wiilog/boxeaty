<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderRepository::class)
 * @ORM\Table(name="`order`")
 */
class Order {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $date;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class)
     */
    private ?Client $client;

    /**
     * @ORM\ManyToOne(targetEntity=Location::class)
     */
    private ?Location $location;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private ?User $user;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="orders")
     */
    private Collection $boxes;

    /**
     * @ORM\ManyToMany(targetEntity=DepositTicket::class, inversedBy="orders")
     */
    private Collection $depositTickets;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private ?string $boxPrice;

    /**
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    private ?string $depositTicketPrice;

    public function __construct() {
        $this->boxes = new ArrayCollection();
        $this->depositTickets = new ArrayCollection();
    }

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

    public function getClient(): ?Client {
        return $this->client;
    }

    public function setClient(?Client $client): self {
        $this->client = $client;
        return $this;
    }

    public function getLocation(): ?Location {
        return $this->location;
    }

    public function setLocation(?Location $location): self {
        $this->location = $location;
        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;
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
            $box->addOrder($this);
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        if ($this->boxes->removeElement($box)) {
            $box->removeOrder($this);
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


    /**
     * @return Collection|DepositTicket[]
     */
    public function getDepositTickets(): Collection {
        return $this->depositTickets;
    }

    public function addDepositTicket(DepositTicket $depositTicket): self {
        if (!$this->depositTickets->contains($depositTicket)) {
            $this->depositTickets[] = $depositTicket;
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self {
        $this->depositTickets->removeElement($depositTicket);

        return $this;
    }

    public function setDepositTickets(?array $depositTickets): self {
        foreach($this->getDepositTickets()->toArray() as $depositTicket) {
            $this->removeDepositTicket($depositTicket);
        }

        $this->depositTickets = new ArrayCollection();
        foreach($depositTickets as $depositTicket) {
            $this->addDepositTicket($depositTicket);
        }

        return $this;
    }

    public function getBoxPrice(): ?string {
        return $this->boxPrice;
    }

    public function setBoxPrice(?string $boxPrice): self {
        $this->boxPrice = $boxPrice;
        return $this;
    }

    public function getDepositTicketPrice(): ?string {
        return $this->depositTicketPrice;
    }

    public function setDepositTicketPrice(?string $depositTicketPrice): self {
        $this->depositTicketPrice = $depositTicketPrice;
        return $this;
    }

}
