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
    public const ROLE_ADMIN = "SUPER_ADMINISTRATEUR";

    public const GENERAL_EXPORT = "GENERAL_EXPORT";
    public const MANAGE_SETTINGS = "MANAGE_SETTINGS";
    public const MANAGE_USERS = "MANAGE_USERS";
    public const MANAGE_ROLES = "MANAGE_ROLES";
    public const MANAGE_QUALITIES = "MANAGE_QUALITIES";
    public const MANAGE_IMPORTS = "MANAGE_IMPORTS";

    public const MANAGE_CLIENTS = "MANAGE_CLIENTS";
    public const MANAGE_LOCATIONS = "MANAGE_LOCATIONS";
    public const MANAGE_GROUPS = "MANAGE_GROUPS";
    public const MANAGE_BOX_TYPES = "MANAGE_BOX_TYPES";

    public const MANAGE_BOXES = "MANAGE_BOXES";
    public const MANAGE_MOVEMENTS = "MANAGE_MOVEMENTS";
    public const MANAGE_DEPOSIT_TICKETS = "MANAGE_DEPOSIT_TICKETS";
    public const MANAGE_ORDERS = "MANAGE_ORDERS";

    public const ADDITIONAL_PERMISSIONS = [
        self::ALLOW_EDIT_OWN_GROUP_ONLY,
        self::REDIRECT_NEW_COUNTER_ORDER,
        self::RECEIVE_MAILS_NEW_ACCOUNTS,
    ];

    public const ALLOW_EDIT_OWN_GROUP_ONLY = "ALLOW_EDIT_OWN_GROUP_ONLY";
    public const REDIRECT_NEW_COUNTER_ORDER = "REDIRECT_NEW_COUNTER_ORDER";
    public const RECEIVE_MAILS_NEW_ACCOUNTS = "RECEIVE_MAILS_NEW_ACCOUNTS";

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
    private ?bool $redirectNewCounterOrder = null;

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

    public function isAllowEditOwnGroupOnly(): ?bool {
        return $this->allowEditOwnGroupOnly;
    }

    public function setAllowEditOwnGroupOnly(?bool $allowEditOwnGroupOnly): self {
        $this->allowEditOwnGroupOnly = $allowEditOwnGroupOnly;
        return $this;
    }

    public function getRedirectNewCounterOrder(): ?bool {
        return $this->redirectNewCounterOrder;
    }

    public function setRedirectNewCounterOrder(?bool $redirectNewCounterOrder): self {
        $this->redirectNewCounterOrder = $redirectNewCounterOrder;
        return $this;
    }

    public function isReceiveMailsNewAccounts(): ?bool {
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
