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
     * @ORM\Column(type="float")
     */
    private ?string $totalBoxAmount;

    /**
     * @ORM\Column(type="float")
     */
    private ?string $totalDepositTicketAmount;

    /**
     * @ORM\Column(type="float")
     */
    private ?string $totalCost;

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
        }

        return $this;
    }

    public function removeBox(Box $box): self {
        $this->boxes->removeElement($box);

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

    public function getTotalBoxAmount(): ?string {
        return $this->totalBoxAmount;
    }

    public function getTotalBoxAmountFormated(): ?string {
        return $this->totalBoxAmount . " €";
    }

    public function setTotalBoxAmount(?string $totalBoxAmount): self {
        $this->totalBoxAmount = $totalBoxAmount;
        return $this;
    }

    public function getTotalDepositTicketAmount(): ?string {
        return $this->totalDepositTicketAmount;
    }

    public function getTotalDepositTicketAmountFormated(): ?string {
        return $this->totalDepositTicketAmount. " €";
    }

    public function setTotalDepositTicketAmount(?string $totalDepositTicketAmount): self {
        $this->totalDepositTicketAmount = $totalDepositTicketAmount;
        return $this;
    }

    public function getTotalCost(): ?float {
        return $this->totalCost;
    }

    public function setTotalCost(float $totalCost): self {
        $this->totalCost = $totalCost;

        return $this;
    }

    public function getTotalCostFormated(): ?string {
        return $this->getTotalCost(). " €";
    }
}
