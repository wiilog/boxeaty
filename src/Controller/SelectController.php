<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SelectController extends AbstractController {

    /**
     * @Route("/select/box", name="ajax_select_boxes", options={"expose": true})
     */
    public function boxes(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Box::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/client", name="ajax_select_clients", options={"expose": true})
     */
    public function clients(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Client::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/user", name="ajax_select_users", options={"expose": true})
     */
    public function users(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(User::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

}
