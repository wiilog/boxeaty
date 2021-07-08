<?php

namespace App\Entity;

use App\Repository\OrderTypeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OrderTypeRepository::class)
 */
class OrderType {

    public const PURCHASE_TRADE = "PURCHASE_TRADE";
    public const AUTONOMOUS_MANAGEMENT = "AUTONOMOUS_MANAGEMENT";
    public const ONE_TIME_SERVICE = "ONE_TIME_SERVICE";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", unique=true, length=255)
     */
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    public function getId(): ?int
    {
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

}
