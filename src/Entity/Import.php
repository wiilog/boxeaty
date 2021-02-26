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

    public const NUMBER = "number";
    public const LOCATION = "location";
    public const KIOSK = "kiosk";
    public const STATE = "state";
    public const QUALITY = "quality";
    public const OWNER = "owner";
    public const TYPE = "type";
    public const COMMENT = "comment";

    public const FIELDS = [
        self::NUMBER => [
            "name" => "Numéro",
            "unique" => true,
            "required" => true,
        ],
        self::LOCATION => [
            "name" => "Emplacement",
        ],
        self::KIOSK => [
            "name" => "Borne",
        ],
        self::STATE => [
            "name" => "Etat",
            "required" => true,
        ],
        self::QUALITY => [
            "name" => "Qualité",
            "required" => true,
        ],
        self::OWNER => [
            "name" => "Propriétaire",
        ],
        self::TYPE => [
            "name" => "Type de box",
            "required" => true,
        ],
        self::COMMENT => [
            "name" => "Commentaire",
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