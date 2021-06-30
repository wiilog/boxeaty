<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\Quality;
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
        $results = $manager->getRepository(Box::class)->getForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/commande-comptoir/box", name="ajax_select_counter_order_boxes", options={"expose": true})
     */
    public function orderBoxes(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Box::class)->getForOrderSelect(
            $request->query->get("term"),
            (array)$request->query->get("items"),
            $this->getUser()
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/commande-comptoir/ticket-consigne", name="ajax_select_counter_order_deposit_tickets", options={"expose": true})
     */
    public function orderDepositTickets(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(DepositTicket::class)->getForOrderSelect(
            $request->query->get("term"),
            (array)$request->query->get("items"),
            $this->getUser()
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/externe/select/group", name="ajax_select_groups", options={"expose": true})
     */
    public function groups(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Group::class)->getForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/client", name="ajax_select_clients", options={"expose": true})
     */
    public function clients(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Client::class)->getForSelect(
            $request->query->get("term"),
            $request->query->get("groups"),
            $this->getUser()
        );

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/multi-site", name="ajax_select_multi_sites", options={"expose": true})
     */
    public function multiSite(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Client::class)->getMultiSiteForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/utilisateur", name="ajax_select_users", options={"expose": true})
     */
    public function users(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(User::class)->getForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/type", name="ajax_select_type", options={"expose": true})
     */
    public function types(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(BoxType::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/qualite", name="ajax_select_quality", options={"expose": true})
     */
    public function qualities(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Quality::class)->getForSelect($request->query->get("term"));

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/emplacement", name="ajax_select_locations", options={"expose": true})
     */
    public function locations(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Location::class)->getLocationsForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/kiosk", name="ajax_select_kiosks", options={"expose": true})
     */
    public function kiosk(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Location::class)->getKiosksForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

    /**
     * @Route("/select/any-location", name="ajax_select_any_location", options={"expose": true})
     */
    public function anyLocation(Request $request, EntityManagerInterface $manager): Response {
        $results = $manager->getRepository(Location::class)->getAnyForSelect($request->query->get("term"), $this->getUser());

        return $this->json([
            "results" => $results,
        ]);
    }

}
