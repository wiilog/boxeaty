<?php

namespace App\Controller;

use App\Entity\GlobalSetting;
use Doctrine\ORM\EntityManagerInterface;
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
    public function home(EntityManagerInterface $manager): Response {
        $defaultCrateTypeId = $manager->getRepository(GlobalSetting::class)->getValue(GlobalSetting::DEFAULT_CRATE_TYPE);

        return $this->render("home.html.twig", [
            'hasDefaultCrate' => $defaultCrateTypeId ? 1 : 0,
        ]);
    }

}
