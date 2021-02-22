<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ClientRepository::class)
 */
class Client {

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text")
     */
    private ?string $address = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $phoneNumber = null;

    /**
     * @ORM\ManyToOne(targetEntity=Group::class, inversedBy="clients")
     * @ORM\JoinColumn(name="`group`", nullable=false)
     */
    private ?Group $group = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $contact = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $isMultiSite = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $allowAllDepositTickets = null;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="clients")
     */
    private ?Client $linkedMultiSite = null;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="clients")
     */
    private Collection $users;

    /**
     * @ORM\OneToMany(targetEntity=Kiosk::class, mappedBy="client")
     */
    private Collection $kiosks;

    /**
     * @ORM\OneToMany(targetEntity=Client::class, mappedBy="linkedMultiSite")
     */
    private Collection $clients;

    public function __construct() {
        $this->users = new ArrayCollection();
        $this->kiosks = new ArrayCollection();
        $this->clients = new ArrayCollection();
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

    public function getAddress(): ?string {
        return $this->address;
    }

    public function setAddress(string $address): self {
        $this->address = $address;

        return $this;
    }

    public function getGroup(): ?Group {
        return $this->group;
    }

    public function setGroup(?Group $group): self {
        $this->group = $group;

        return $this;
    }

    public function getPhoneNumber(): ?string {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getContact(): ?User {
        return $this->contact;
    }

    public function setContact(?User $contact): self {
        $this->contact = $contact;

        return $this;
    }

    public function isMultiSite(): ?bool {
        return $this->isMultiSite;
    }

    public function setIsMultiSite(bool $isMultiSite): self {
        $this->isMultiSite = $isMultiSite;

        return $this;
    }

    public function getAllowAllDepositTickets(): ?bool {
        return $this->allowAllDepositTickets;
    }

    public function setAllowAllDepositTickets(bool $allowAllDepositTickets): self {
        $this->allowAllDepositTickets = $allowAllDepositTickets;

        return $this;
    }

    public function getLinkedMultiSite(): ?self {
        return $this->linkedMultiSite;
    }

    public function setLinkedMultiSite(?self $linkedMultiSite): self {
        $this->linkedMultiSite = $linkedMultiSite;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection {
        return $this->users;
    }

    public function addUser(User $user): self {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addClient($this);
        }

        return $this;
    }

    public function removeUser(User $user): self {
        if ($this->users->removeElement($user)) {
            $user->removeClient($this);
        }

        return $this;
    }

    /**
     * @return Collection|Kiosk[]
     */
    public function getKiosks(): Collection {
        return $this->kiosks;
    }

    public function addKiosk(Kiosk $kiosk): self {
        if (!$this->kiosks->contains($kiosk)) {
            $this->kiosks[] = $kiosk;
            $kiosk->setClient($this);
        }

        return $this;
    }

    public function removeKiosk(Kiosk $kiosk): self {
        if ($this->kiosks->removeElement($kiosk)) {
            // set the owning side to null (unless already changed)
            if ($kiosk->getClient() === $this) {
                $kiosk->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|self[]
     */
    public function getClients(): Collection {
        return $this->clients;
    }

    public function addClient(self $client): self {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->setLinkedMultiSite($this);
        }

        return $this;
    }

    public function removeClient(self $client): self {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getLinkedMultiSite() === $this) {
                $client->setLinkedMultiSite(null);
            }
        }

        return $this;
    }

}
