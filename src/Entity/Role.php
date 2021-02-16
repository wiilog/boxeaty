<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RoleRepository::class)
 */
class Role {

    public const ROLE_NO_ACCESS = "AUCUN_ACCES";
    public const ROLE_ADMIN = "ADMINISTRATEUR";

    public const MANAGE_SETTINGS = "MANAGE_SETTINGS";
    public const MANAGE_USERS = "MANAGE_USERS";
    public const MANAGE_ROLES = "MANAGE_ROLES";
    public const MANAGE_GROUPS = "MANAGE_GROUPS";
    public const MANAGE_CLIENTS = "MANAGE_CLIENTS";

    public const CREATE_BOX = "CREATE_BOX";
    public const EDIT_BOX = "EDIT_BOX";
    public const DEACTIVATE_BOX = "DEACTIVATE_BOX";
    public const IMPORT_BOX = "IMPORT_BOX";
    public const EXPORT_BOX = "EXPORT_BOX";
    public const CHECKOUT = "CHECKOUT";
    public const DELETE_MOVEMENT = "DELETE_MOVEMENT";

    public const MANAGE_CLIENT = "MANAGE_CLIENT";
    public const DEACTIVATE_CLIENT = "DEACTIVATE_CLIENT";
    public const DELETE_CLIENT = "DELETE_CLIENT";
    public const MANAGE_LOCATIONS = "MANAGE_LOCATIONS";

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
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

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

    public function getName(): ?string {
        return $this->name;
    }

    public function setName(string $name): self {
        $this->name = $name;

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
