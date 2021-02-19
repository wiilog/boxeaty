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
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="clients")
     */
    private Collection $users;

    public function __construct() {
        $this->users = new ArrayCollection();
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

    /**
     * @return Collection|User[]
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->addClient($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            $user->removeClient($this);
        }

        return $this;
    }

}
