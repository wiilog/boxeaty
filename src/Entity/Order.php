<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderRepository::class)
 * @ORM\Table(name="`order`")
 */
class Order
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\ManyToMany(targetEntity=Box::class, inversedBy="orders")
     */
    private $boxes;

    /**
     * @ORM\ManyToMany(targetEntity=DepositTicket::class, inversedBy="orders")
     */
    private $depositTickets;

    /**
     * @ORM\Column(type="float")
     */
    private $totalCost;

    public function __construct()
    {
        $this->boxes = new ArrayCollection();
        $this->depositTickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    /**
     * @return Collection|Box[]
     */
    public function getBoxes(): Collection
    {
        return $this->boxes;
    }

    public function addBox(Box $box): self
    {
        if (!$this->boxes->contains($box)) {
            $this->boxes[] = $box;
        }

        return $this;
    }

    public function removeBox(Box $box): self
    {
        $this->boxes->removeElement($box);

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
        }

        return $this;
    }

    public function removeDepositTicket(DepositTicket $depositTicket): self
    {
        $this->depositTickets->removeElement($depositTicket);

        return $this;
    }

    public function getTotalCost(): ?float
    {
        return $this->totalCost;
    }

    public function setTotalCost(float $totalCost): self
    {
        $this->totalCost = $totalCost;

        return $this;
    }
}
