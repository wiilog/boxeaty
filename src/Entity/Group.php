<?php

namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GroupRepository::class)
 * @ORM\Table(name="`group`")
 */
class Group {

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
     * @ORM\OneToMany(targetEntity=Client::class, mappedBy="group")
     */
    private Collection $clients;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="groups")
     */
    private Collection $users;

    public function __construct() {
        $this->clients = new ArrayCollection();
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

    /**
     * @return Collection|Client[]
     */
    public function getClients(): Collection {
        return $this->clients;
    }

    public function addClient(Client $client): self {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->setGroup($this);
        }

        return $this;
    }

    public function removeClient(Client $client): self {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getGroup() === $this) {
                $client->setGroup(null);
            }
        }

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
            $user->addGroup($this);
        }

        return $this;
    }

    public function removeUser(User $user): self {
        if ($this->users->removeElement($user)) {
            $user->removeGroup($this);
        }

        return $this;
    }

}
