<?php

namespace App\Entity;

use App\Repository\WorkFreeDayRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=WorkFreeDayRepository::class)
 */
class WorkFreeDay {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $day = null;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $month = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDay(): ?int {
        return $this->day;
    }

    public function setDay(int $day): self {
        $this->day = $day;

        return $this;
    }

    public function getMonth(): ?int {
        return $this->month;
    }

    public function setMonth(int $month): self {
        $this->month = $month;

        return $this;
    }
}
