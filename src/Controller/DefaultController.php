<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController {

    /**
     * @Route("/api/ping", name="ping")
     */
    public function ping(): Response {
        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/accueil", name="home", options={"expose": true})
     */
    public function home(): Response {
        return $this->render("home.html.twig");
    }

}
