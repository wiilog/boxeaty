<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 */
class User implements UserInterface {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
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
     * @ORM\Column(type="boolean", options={"default": false})
     */
    private ?bool $active = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getUsername(): ?string {
        return $this->email;
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

    public function getRoles() {
        return [];
    }

    public function getSalt() {
        return null;
    }

    public function eraseCredentials() {

    }

    public function isActive(): ?bool {
        return $this->active;
    }

    public function setActive(bool $active): self {
        $this->active = $active;

        return $this;
    }

}
