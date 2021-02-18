<?php

namespace App\Controller;

use App\Annotation\HasPermission;
use App\Entity\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/accueil", name="home")
     */
    public function home(): Response {
        return $this->render("example.html.twig");
    }

    /**
     * @Route("/lost", name="missing_route")
     */
    public function missingRoute(): Response {
        return $this->render("example.html.twig");
    }

}
