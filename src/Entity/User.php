<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User implements UserInterface {

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
    private ?string $username = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $email = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $password = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $lastLogin = null;

    /**
     * @ORM\ManyToOne(targetEntity=Role::class, inversedBy="users")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Role $role = null;

    /**
     * @ORM\OneToMany(targetEntity=TrackingMovement::class, mappedBy="user")
     */
    private Collection $trackingMovements;

    /**
     * @ORM\ManyToMany(targetEntity=Client::class, inversedBy="users")
     */
    private Collection $clients;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, inversedBy="users")
     */
    private Collection $groups;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $resetToken;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $resetTokenExpiration;

    public function __construct() {
        $this->clients = new ArrayCollection();
        $this->trackingMovements = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getUsername(): ?string {
        return $this->username;
    }

    public function setUsername(string $username): self {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(string $email): self {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string {
        return $this->password;
    }

    public function setPassword(string $password): self {
        $this->password = $password;

        return $this;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getLastLogin(): ?DateTime {
        return $this->lastLogin;
    }

    public function setLastLogin(DateTime $lastLogin): self {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getRole(): ?Role {
        return $this->role;
    }

    public function setRole(?Role $role): self {
        $this->role = $role;

        return $this;
    }

    /**
     * @return Collection|TrackingMovement[]
     */
    public function getTrackingMovements(): Collection {
        return $this->trackingMovements;
    }

    public function addTrackingMovement(TrackingMovement $trackingMovement): self {
        if (!$this->trackingMovements->contains($trackingMovement)) {
            $this->trackingMovements[] = $trackingMovement;
            $trackingMovement->setUser($this);
        }

        return $this;
    }

    public function removeTrackingMovement(TrackingMovement $trackingMovement): self {
        if ($this->trackingMovements->removeElement($trackingMovement)) {
            // set the owning side to null (unless already changed)
            if ($trackingMovement->getUser() === $this) {
                $trackingMovement->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Client[]
     */
    public function getClients(): Collection {
        return $this->clients;
    }

    public function addClient(Client $client): self {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
        }

        return $this;
    }

    public function removeClient(Client $client): self {
        $this->clients->removeElement($client);

        return $this;
    }

    public function setClients(array $clients): self {
        if ($this->clients) {
            foreach ($this->clients as $client) {
                $client->removeUser($this);
            }
        }

        $this->clients = new ArrayCollection($clients);
        foreach ($this->clients as $client) {
            $client->addUser($this);
        }

        return $this;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection {
        return $this->groups;
    }

    public function addGroup(Group $group): self {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
        }

        return $this;
    }

    public function removeGroup(Group $group): self {
        $this->groups->removeElement($group);

        return $this;
    }

    public function setGroups(array $groups): self {
        if ($this->groups) {
            foreach ($this->groups as $group) {
                $group->removeUser($this);
            }
        }

        $this->groups = new ArrayCollection($groups);
        foreach ($this->groups as $group) {
            $group->addUser($this);
        }

        return $this;
    }

    public function getRoles() {
        return [];
    }

    public function getSalt() {
        return null;
    }

    public function eraseCredentials() {

    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetTokenExpiration(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiration;
    }

    public function setResetTokenExpiration(?\DateTimeInterface $resetTokenExpiration): self
    {
        $this->resetTokenExpiration = $resetTokenExpiration;

        return $this;
    }

}
