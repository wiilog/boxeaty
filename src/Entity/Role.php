<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RoleRepository::class)
 */
class Role {

    public const ROLE_NO_ACCESS = "AUCUN_ACCES";
    public const ROLE_ADMIN = "ADMINISTRATEUR";

    public const GENERAL_EXPORT = "GENERAL_EXPORT";
    public const MANAGE_SETTINGS = "MANAGE_SETTINGS";
    public const MANAGE_USERS = "MANAGE_USERS";
    public const MANAGE_ROLES = "MANAGE_ROLES";
    public const MANAGE_QUALITIES = "MANAGE_QUALITIES";
    public const MANAGE_IMPORTS = "MANAGE_IMPORTS";

    public const MANAGE_CLIENTS = "MANAGE_CLIENTS";
    public const DEACTIVATE_CLIENT = "DEACTIVATE_CLIENT";
    public const DELETE_CLIENT = "DELETE_CLIENT";
    public const MANAGE_LOCATIONS = "MANAGE_LOCATIONS";
    public const MANAGE_GROUPS = "MANAGE_GROUPS";
    public const MANAGE_KIOSKS = "MANAGE_KIOSKS";
    public const MANAGE_BOX_TYPES = "MANAGE_BOX_TYPES";

    public const CREATE_BOX = "CREATE_BOX";
    public const EDIT_BOX = "EDIT_BOX";
    public const DEACTIVATE_BOX = "DEACTIVATE_BOX";
    public const IMPORT_BOX = "IMPORT_BOX";
    public const EXPORT_BOX = "EXPORT_BOX";
    public const CHECKOUT = "CHECKOUT";
    public const MANAGE_MOVEMENTS = "MANAGE_MOVEMENTS";
    public const DELETE_MOVEMENT = "DELETE_MOVEMENT";
    public const MANAGE_DEPOSIT_TICKETS = "MANAGE_DEPOSIT_TICKETS";
    public const DELETE_DEPOSIT_TICKET = "DELETE_DEPOSIT_TICKET";
    public const MANAGE_BOXES = "MANAGE_BOX";
    public const DELETE_BOX = "DELETE_BOX";

    use Active;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $permissions = [];

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $allowEditOwnGroupOnly = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $redirectUserNewCommand = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $receiveMailsNewAccounts = null;

    /**
     * @ORM\OneToMany(targetEntity=User::class, mappedBy="role")
     */
    private Collection $users;

    public function __construct() {
        $this->users = new ArrayCollection();
    }

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

    public function getAllowEditOwnGroupOnly(): ?bool {
        return $this->allowEditOwnGroupOnly;
    }

    public function setAllowEditOwnGroupOnly(?bool $allowEditOwnGroupOnly): self {
        $this->allowEditOwnGroupOnly = $allowEditOwnGroupOnly;
        return $this;
    }

    public function getRedirectUserNewCommand(): ?bool {
        return $this->redirectUserNewCommand;
    }

    public function setRedirectUserNewCommand(?bool $redirectUserNewCommand): self {
        $this->redirectUserNewCommand = $redirectUserNewCommand;
        return $this;
    }

    public function getReceiveMailsNewAccounts(): ?bool {
        return $this->receiveMailsNewAccounts;
    }

    public function setReceiveMailsNewAccounts(?bool $receiveMailsNewAccounts): self {
        $this->receiveMailsNewAccounts = $receiveMailsNewAccounts;
        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getUsers() {
        return $this->users;
    }

}
