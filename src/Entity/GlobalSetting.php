<?php

namespace App\Entity;

use App\Repository\GlobalSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=GlobalSettingRepository::class)
 */
class GlobalSetting {

    public const CSV_EXPORTS_ENCODING = "CSV_EXPORTS_ENCODING";
    public const SETTING_CODE = "SETTING_CODE";
    public const EMPTY_KIOSK_CODE = "EMPTY_KIOSK_CODE";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $value = null;

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

    public function getValue(): ?string {
        return $this->value;
    }

    public function setValue(?string $value): self {
        $this->value = $value;

        return $this;
    }

}
