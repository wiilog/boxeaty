<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RoleRepository::class)
 */
class Role {

    public const ROLE_NO_ACCESS = "ROLE_NO_ACCESS";
    public const ROLE_ADMIN = "ROLE_ADMIN";

    public const MANAGE_USERS = "MANAGE_USERS";
    public const MANAGE_ROLES = "MANAGE_ROLES";
    public const MANAGE_GROUPS = "MANAGE_GROUPS";
    public const MANAGE_CLIENTS = "MANAGE_CLIENTS";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $label = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $active = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $permissions = [];

    public function getId(): ?int {
        return $this->id;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function setCode(string $code): self {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): ?string {
        return $this->label;
    }

    public function setLabel(string $label): self {
        $this->label = $label;

        return $this;
    }

    public function getActive(): ?bool {
        return $this->active;
    }

    public function setActive(bool $active): self {
        $this->active = $active;

        return $this;
    }

    public function getPermissions(): ?array {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self {
        $this->permissions = $permissions;

        return $this;
    }

}
