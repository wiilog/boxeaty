<?php

namespace App\Entity;

use App\Repository\DeliveryMethodRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=DeliveryMethodRepository::class)
 */
class DeliveryMethod {

    public const BIKE = 0;
    public const LIGHT_TRUCK = 1;
    public const HEAVY_TRUCK = 2;

    public const TRANSPORT_TYPES = [
        self::BIKE => "bike",
        self::LIGHT_TRUCK => "light-truck",
        self::HEAVY_TRUCK => "heavy-truck",
    ];
    const DEFAULT_DATATABLE_ORDER = [["name", "asc"]] ;

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
    private ?string $icon = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $deleted = false;

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

    public function getIcon(): ?string {
        return $this->icon;
    }

    public function setIcon(string $icon): self {
        $this->icon = $icon;

        return $this;
    }

    public function getDeleted(): ?bool {
        return $this->deleted;
    }

    public function setDeleted(?bool $deleted): self {
        $this->deleted = $deleted;

        return $this;
    }

}
