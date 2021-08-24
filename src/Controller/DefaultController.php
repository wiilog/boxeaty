<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/accueil/{redirection}", name="home", options={"expose": true})
     */
    public function home($redirection = 0): Response {
        return $this->render("home.html.twig",[
        "redirection" => $redirection, ]);
    }

}
