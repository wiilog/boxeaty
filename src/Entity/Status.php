<?php

namespace App\Entity;

use App\Repository\StatusRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StatusRepository::class)
 */
class Status {

    public const CATEGORY_ORDER = "ORDER";
    public const CODE_ORDER_TO_VALIDATE = "ORDER_TO_VALIDATE";
    public const CODE_ORDER_PLANNED = "ORDER_PLANNED";
    public const CODE_ORDER_TRANSIT = "ORDER_TRANSIT";
    public const CODE_ORDER_FINISHED = "ORDER_FINISHED";

    public const CATEGORY_ROUND = "DELIVERY_ROUND";
    public const CODE_ROUND_CREATED = "DELIVERY_ROUND_CREATED";
    public const CODE_ROUND_AWAITING_DELIVERER = "DELIVERY_ROUND_AWAITING_DELIVERER";
    public const CODE_ROUND_TAKEN_DELIVERER = "DELIVERY_ROUND_TAKEN_DELIVERER";
    public const CODE_ROUND_FINISHED = "DELIVERY_ROUND_FINISHED";

    public const CATEGORY_PREPARATION = "PREPARATION";
    public const CODE_PREPARATION_PREPARING = "PREPARATION_PREPARING";
    public const CODE_PREPARATION_PREPARED = "PREPARATION_PREPARED";

    public const CATEGORY_DELIVERY = "DELIVERY";
    public const CODE_DELIVERY_PLANNED = "DELIVERY_PLANNED";
    public const CODE_DELIVERY_PREPARING = "DELIVERY_PREPARING";
    public const CODE_DELIVERY_AWAITING_DELIVERER = "DELIVERY_AWAITING_DELIVERER";
    public const CODE_DELIVERY_TRANSIT = "DELIVERY_TRANSIT";
    public const CODE_DELIVERY_DELIVERED = "DELIVERY_DELIVERED";

    public const CATEGORY_COLLECT = "COLLECT";
    public const CODE_COLLECT_TRANSIT = "COLLECT_TRANSIT";
    public const CODE_COLLECT_FINISHED = "COLLECT_FINISHED";

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
