<?php

namespace App\Entity\Utils;

use App\Entity\Status;
use Doctrine\ORM\Mapping as ORM;

trait StatusTrait {

    /**
     * @ORM\ManyToOne(targetEntity=Status::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Status $status = null;

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function hasStatusCode(string $code): bool {
        return (
            $this->getStatus()
            && $this->getStatus()->getCode() === $code
        );
    }

    public function setStatus(Status $status): self {
        $this->status = $status;
        return $this;
    }

}
