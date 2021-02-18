<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\Group;
use App\Entity\Kiosk;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/tickets-consigne")
 */
class DepositTicketController extends AbstractController {

    /**
     * @Route("/liste", name="deposit_tickets_list")
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function list(EntityManagerInterface $manager): Response {
        $kiosks = $manager->getRepository(Kiosk::class)->findAll();

        return $this->render("tracking/deposit_ticket/index.html.twig", [
            "new_deposit_ticket" => new DepositTicket(),
            "kiosks" => $kiosks
        ]);
    }

    /**
     * @Route("/api", name="deposit_tickets_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $depositTickets = $manager->getRepository(DepositTicket::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($depositTickets["data"] as $depositTicket) {
            $data[] = [
                "id" => $depositTicket->getId(),
                "creationDate" => $depositTicket->getCreationDate()->format('Y/m/d H:i') ?? '',
                "kiosk" => $depositTicket->getKiosk() ? $depositTicket->getKiosk()->getName() : '',
                "validityDate" => $depositTicket->getValidityDate()->format('Y/m/d H:i') ?? '',
                "number" => $depositTicket->getNumber() ?? '',
                "useDate" => $depositTicket->getUseDate()->format('Y/m/d H:i') ?? '',
                "location" => $depositTicket->getLocation() ? $depositTicket->getLocation()->getName() : '',
                "condition" => $depositTicket->getCondition() ?? '',
                "actions" => $this->renderView("tracking/deposit_ticket/datatable_actions.html.twig"),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $depositTickets["total"],
            "recordsFiltered" => $depositTickets["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="deposit_ticket_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $group = $manager->getRepository(Group::class)->find($content->group);
        $user = $manager->getRepository(User::class)->find($content->user);
        $existing = $manager->getRepository(Client::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("email", "Ce client existe déjà");
        }

        if($form->isValid()) {
            $client = new Client();
            $client
                ->setName($content->name)
                ->setAddress($content->address)
                ->setPhoneNumber($content->phoneNumber)
                ->setActive($content->active)
                ->setGroup($group)
                ->setUser($user);

            $manager->persist($client);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Client créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{ticket-consigne}", name="deposit_ticket_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function editTemplate(EntityManagerInterface $manager, Client $client): Response {
        $groups = $manager->getRepository(Group::class)->findAll();
        $users = $manager->getRepository(User::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("client_edit", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/edit.html.twig", [
                "client" => $client,
                "groups" => $groups,
                "users" => $users
            ])
        ]);
    }

    /**
     * @Route("/modifier/{ticket-consigne}", name="deposit_ticket_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Client $client): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $group = $manager->getRepository(Group::class)->find($content->group);
        $user = $manager->getRepository(User::class)->find($content->user);
        $existing = $manager->getRepository(Client::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $client) {
            $form->addError("email", "Un autre client avec ce nom existe déjà");
        }

        if($form->isValid()) {
            $client
                ->setName($content->name)
                ->setAddress($content->address)
                ->setPhoneNumber($content->phoneNumber)
                ->setActive($content->active)
                ->setGroup($group)
                ->setUser($user);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Client modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/export", name="deposit_tickets_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $depositTickets = $manager->getRepository(DepositTicket::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $header = array_merge([
            "Date de création",
            "Lieu de création",
            "Date de validité",
            "Numéro de consigne",
            "Date et heure d'utilisation de la consigne",
            "Emplacement de la consigne",
            "Etat",
        ]);

        return $exportService->export(function($output) use ($exportService, $depositTickets) {
            foreach ($depositTickets as $depositTicket) {
                $exportService->putLine($output, $depositTicket);
            }
        }, "export-tickets-consigne-$today.csv", $header);
    }

}
