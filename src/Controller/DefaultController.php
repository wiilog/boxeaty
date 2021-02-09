<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/accueil", name="home")
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
