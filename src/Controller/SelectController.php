<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Kiosk;
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
     * @Route("/externe/select/group", name="ajax_select_groups", options={"expose": true})
     */
    public function groups(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Group::class)->getForSelect($request->query->get("term"));

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
     * @Route("/select/multi-site", name="ajax_select_multi_sites", options={"expose": true})
     */
    public function multiSite(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Client::class)->getMultiSiteForSelect($request->query->get("term"));

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

    /**
     * @Route("/select/kios", name="ajax_select_kiosks", options={"expose": true})
     */
    public function kiosk(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Kiosk::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

}
