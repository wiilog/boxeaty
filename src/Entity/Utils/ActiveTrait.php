<?php

namespace App\Entity\Utils;

use Doctrine\ORM\Mapping as ORM;

trait ActiveTrait {

    /**
     * @ORM\Column(type="boolean")
     */
    private ?bool $active = true;

    public function isActive(): ?bool {
        return $this->active;
    }

    public function setActive(bool $active): self {
        $this->active = $active;

        return $this;
    }

}
