<?php

namespace App\Entity;

use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AttachmentRepository::class)
 */
class Attachment {

    public const TYPE_BOX_TYPE_IMAGE = "TYPE_BOX_TYPE_IMAGE";
    public const TYPE_DELIVERY_SIGNATURE = "TYPE_DELIVERY_SIGNATURE";
    public const TYPE_DELIVERY_PHOTO = "TYPE_DELIVERY_PHOTO";

    public const DIRECTORY_PATHS = [
        self::TYPE_BOX_TYPE_IMAGE => "persistent/box_type",
        self::TYPE_DELIVERY_SIGNATURE => "persistent/delivery_signatures",
        self::TYPE_DELIVERY_PHOTO => "persistent/delivery_photos",
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
    private ?string $originalName = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $serverName = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private ?string $type = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getOriginalName(): ?string {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self {
        $this->originalName = $originalName;

        return $this;
    }

    public function getServerName(): ?string {
        return $this->serverName;
    }

    public function setServerName(?string $serverName): self {
        $this->serverName = $serverName;

        return $this;
    }

    public function getType(): ?string {
        return $this->type;
    }

    public function setType(?string $type): self {
        $this->type = $type;

        return $this;
    }

    public function getPath(): string {
        return Attachment::DIRECTORY_PATHS[$this->type] . '/' . $this->serverName;
    }

}
