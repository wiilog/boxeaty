<?php

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StatusRepository::class)
 */
class Status {

    public const ORDER = "ORDER";
    public const ORDER_TO_VALIDATE = "ORDER_TO_VALIDATE";
    public const ORDER_PLANNED = "ORDER_PLANNED";
    public const ORDER_TRANSIT = "ORDER_TRANSIT";
    public const ORDER_FINISHED = "ORDER_FINISHED";

    public const ROUND = "DELIVERY_ROUND";
    public const ROUND_CREATED = "DELIVERY_ROUND_CREATED";
    public const ROUND_AWAITING_DELIVERER = "DELIVERY_ROUND_AWAITING_DELIVERER";
    public const ROUND_TAKEN_DELIVERER = "DELIVERY_ROUND_TAKEN_DELIVERER";
    public const ROUND_FINISHED = "DELIVERY_ROUND_FINISHED";

    public const PREPARATION = "PREPARATION";
    public const PREPARATION_PREPARING = "PREPARATION_PREPARING";
    public const PREPARATION_PREPARED = "PREPARATION_PREPARED";

    public const DELIVERY = "DELIVERY";
    public const DELIVERY_PLANNED = "DELIVERY_PLANNED";
    public const DELIVERY_PREPARING = "DELIVERY_PREPARING";
    public const DELIVERY_AWAITING_DELIVERER = "DELIVERY_AWAITING_DELIVERER";
    public const DELIVERY_TRANSIT = "DELIVERY_TRANSIT";
    public const DELIVERY_DELIVERED = "DELIVERY_DELIVERED";

    public const COLLECT = "COLLECT";
    public const COLLECT_TRANSIT = "COLLECT_TRANSIT";
    public const COLLECT_FINISHED = "COLLECT_FINISHED";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $category = null;

    /**
     * @ORM\Column(type="string", unique=true, length=255)
     */
    private ?string $code = null;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private ?string $name = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getCategory(): ?string {
        return $this->category;
    }

    public function setCategory(string $category): self {
        $this->category = $category;

        return $this;
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
