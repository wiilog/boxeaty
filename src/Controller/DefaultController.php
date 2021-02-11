<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/accueil", name="home")
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function home() {
        return $this->render("example.html.twig");
    }

    /**
     * @Route("/lost", name="missing_route")
     */
    public function missingRoute() {
        return $this->render("example.html.twig");
    }

}
