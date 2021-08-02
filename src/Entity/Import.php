<?php

namespace App\Entity;

use App\Repository\ImportRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ImportRepository::class)
 */
class Import {

    public const TYPE_BOX = "box";
    public const TYPE_LOCATION = "location";

    public const UPCOMING = 1;
    public const INSTANT = 2;
    public const RUNNING = 3;
    public const COMPLETED = 4;
    public const CANCELLED = 5;

    public const NAMES = [
        self::UPCOMING => "Planifié",
        self::INSTANT => "Immédiat",
        self::RUNNING => "En cours",
        self::COMPLETED => "Terminé",
        self::CANCELLED => "Annulé",
    ];

    public const BOX_OR_CRATE = "box_crate";
    public const NUMBER = "number";
    public const NAME = "name";
    public const LOCATION = "location";
    public const STATE = "state";
    public const QUALITY = "quality";
    public const OWNER = "owner";
    public const TYPE = "type";
    public const COMMENT = "comment";
    public const LOCATION_OR_KIOSK = "location_kiosk";
    public const ACTIVE = "active";
    public const DESCRIPTION = "description";
    public const CLIENT = "client";
    public const DEPOSITORY = "depository";
    public const CAPACITY = "capacity";
    public const MESSAGE = "message";

    public const FIELDS = [
        self::TYPE_BOX => self::BOX_FIELDS,
        self::TYPE_LOCATION => self::LOCATION_FIELDS,
    ];

    public const BOX_FIELDS = [
        self::BOX_OR_CRATE => [
            "name" => "Box ou caisse",
        ],
        self::NUMBER => [
            "name" => "Numéro",
            "unique" => true,
            "required" => true,
        ],
        self::LOCATION => [
            "name" => "Emplacement",
        ],
        self::STATE => [
            "name" => "Etat",
        ],
        self::QUALITY => [
            "name" => "Qualité",
        ],
        self::OWNER => [
            "name" => "Propriétaire",
        ],
        self::TYPE => [
            "name" => "Type de Box / Caisse",
        ],
        self::COMMENT => [
            "name" => "Commentaire",
        ],
    ];

    public const LOCATION_FIELDS = [
        self::LOCATION_OR_KIOSK => [
            "name" => "Emplacement ou borne",
        ],
        self::NAME => [
            "name" => "Nom",
            "unique" => true,
            "required" => true,
        ],
        self::DESCRIPTION => [
            "name" => "Description",
        ],
        self::ACTIVE => [
            "name" => "Actif/Inactif",
        ],
        self::CLIENT => [
            "name" => "Client",
        ],
        self::TYPE => [
            "name" => "Type",
        ],
        self::DEPOSITORY => [
            "name" => "Dépôt",
        ],
        self::CAPACITY => [
            "name" => "Capacité",
        ],
        self::MESSAGE => [
            "name" => "Message borne",
        ],
    ];

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
     * @ORM\Column(type="string", length=255)
     */
    private ?string $dataType = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $status = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $creationDate = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $file = null;

    /**
     * @ORM\Column(type="json")
     */
    private $fieldsAssociation = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $executionDate = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $trace = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $creations = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $updates = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $errors = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $user = null;

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

    public function getDataType(): ?string {
        return $this->dataType;
    }

    public function setDataType(string $dataType): self {
        $this->dataType = $dataType;

        return $this;
    }

    public function getStatus(): ?int {
        return $this->status;
    }

    public function setStatus(int $status): self {
        $this->status = $status;

        return $this;
    }

    public function getCreationDate(): ?DateTime {
        return $this->creationDate;
    }

    public function setCreationDate(DateTime $creationDate): self {
        $this->creationDate = $creationDate;

        return $this;
    }

    public function getFile(): ?string {
        return $this->file;
    }

    public function setFile(string $file): self {
        $this->file = $file;

        return $this;
    }

    public function getFieldsAssociation(): ?array {
        return $this->fieldsAssociation;
    }

    public function setFieldsAssociation(array $fieldsAssociation): self {
        $this->fieldsAssociation = $fieldsAssociation;

        return $this;
    }

    public function getExecutionDate(): ?DateTime {
        return $this->executionDate;
    }

    public function setExecutionDate(?DateTime $executionDate): self {
        $this->executionDate = $executionDate;

        return $this;
    }

    public function getTrace(): ?string {
        return $this->trace;
    }

    public function setTrace(?string $trace): self {
        $this->trace = $trace;

        return $this;
    }

    public function getCreations(): ?int {
        return $this->creations;
    }

    public function setCreations(?int $creations): self {
        $this->creations = $creations;

        return $this;
    }

    public function getUpdates(): ?int {
        return $this->updates;
    }

    public function setUpdates(?int $updates): self {
        $this->updates = $updates;

        return $this;
    }

    public function getErrors(): ?int {
        return $this->errors;
    }

    public function setErrors(?int $errors): self {
        $this->errors = $errors;

        return $this;
    }

    public function getUser(): ?User {
        return $this->user;
    }

    public function setUser(?User $user): self {
        $this->user = $user;

        return $this;
    }

}
