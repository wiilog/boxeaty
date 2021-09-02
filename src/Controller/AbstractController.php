<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;

class AbstractController extends SymfonyAbstractController {

    private ?User $user = null;

    public function getUser(): ?User {
        return $this->user ?? parent::getUser();
    }

    public function setUser(?User $user): void {
        $this->user = $user;
    }

}
