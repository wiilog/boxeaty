<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\Location;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Helper\StringHelper;
use App\Repository\DepositTicketRepository;
use App\Service\ExportService;
use App\Service\Mailer;
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
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("tracking/deposit_ticket/index.html.twig", [
            "new_deposit_ticket" => (new DepositTicket())->setNumber(StringHelper::random(5)),
            "initial_deposit_tickets" => $this->api($request, $manager)->getContent(),
            "deposit_tickets_order" => DepositTicketRepository::DEFAULT_DATATABLE_ORDER
        ]);
    }

    /**
     * @Route("/api", name="deposit_tickets_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $depositTickets = $manager->getRepository(DepositTicket::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $data = [];

        /** @var DepositTicket $depositTicket */
        foreach ($depositTickets["data"] as $depositTicket) {
            $box = $depositTicket->getBox();
            $boxType = $box ? $box->getType() : null;
            $totalAmount = $boxType ? $boxType->getPrice() : null;
            $data[] = [
                "id" => $depositTicket->getId(),
                "number" => $depositTicket->getNumber(),
                "creationDate" => FormatHelper::datetime($depositTicket->getCreationDate()),
                "kiosk" => FormatHelper::named($depositTicket->getLocation()),
                "validityDate" => FormatHelper::datetime($depositTicket->getValidityDate()),
                "useDate" => FormatHelper::datetime($depositTicket->getUseDate()) ?: "Inutilisé",
                "client" => FormatHelper::named($depositTicket->getLocation()->getClient()),
                "state" => DepositTicket::NAMES[$depositTicket->getState()] ?? "-",
                "orderUser" => FormatHelper::user($depositTicket->getOrderUser()),
                "depositAmount" => FormatHelper::price($totalAmount),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "deletable" => true,
                ]),
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
     * @param Request $request
     * @param EntityManagerInterface $manager
     * @param Mailer $mailer
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $manager,
                        Mailer $mailer): Response {
        $form = Form::create();

        $depositTicketRepository = $manager->getRepository(DepositTicket::class);
        $content = (object)$request->request->all();
        $kiosk = $manager->getRepository(Location::class)->find($content->location);
        $box = $manager->getRepository(Box::class)->find($content->box);
        $existing = $depositTicketRepository->findOneBy(["number" => $content->number]);
        $alreadyValidTicketOnBoxCount = $depositTicketRepository->count([
            "box" => $box,
            "state" => DepositTicket::VALID,
        ]);

        if (((int) $content->state === DepositTicket::VALID) && $alreadyValidTicketOnBoxCount > 0) {
            $form->addError("state", "Un ticket‑consigne valide existe déjà pour la Box " . "<strong>" . $box->getNumber() . "</strong>");
        }

        if ($existing) {
            $form->addError("number", "Ce ticket‑consigne existe déjà");
        }

        if ($form->isValid()) {
            $depositTicket = new DepositTicket();
            $depositTicket
                ->setBox($box)
                ->setCreationDate(new DateTime())
                ->setLocation($kiosk)
                ->setValidityDate(new DateTime("+{$kiosk->getClient()->getDepositTicketValidity()} month"))
                ->setNumber($content->number)
                ->setState($content->state)
                ->setConsumerEmail($content->emailConsumer ?? null);

            if ($content->state == DepositTicket::SPENT) {
                $depositTicket->setUseDate(new DateTime());
            }

            $manager->persist($depositTicket);
            $manager->flush();

            $client = $depositTicket->getLocation() ? $depositTicket->getLocation()->getClient() : null;
            $depositTicketsClientsCount = $client ? $client->getDepositTicketsClients()->count() : 0;

            $usable = ($depositTicketsClientsCount === 0
                ? "tout le réseau BoxEaty"
                : ($depositTicketsClientsCount === 1
                    ? "le restaurant"
                    : "les restaurants"));

            $mailer->send(
                $depositTicket->getConsumerEmail(),
                "Création d'un ticket‑consigne",
                $this->renderView("emails/deposit_ticket.html.twig",[
                    "ticket" => $depositTicket,
                    "usable" => $usable,
                ])
            );

            return $this->json([
                "success" => true,
                "message" => "Ticket‑consigne <b>{$depositTicket->getNumber()}</b> créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="deposit_ticket_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $depositTicket = $manager->getRepository(DepositTicket::class)->find($content->id);

        if ($depositTicket) {
            $manager->remove($depositTicket);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Ticket‑consigne <strong>{$depositTicket->getNumber()}</strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Le ticket‑consigne n'existe pas"
            ]);
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

        return $exportService->export(function($output) use ($exportService, $depositTickets) {
            foreach ($depositTickets as $depositTicket) {
                $depositTicket["state"] = DepositTicket::NAMES[$depositTicket["state"]];
                $exportService->putLine($output, $depositTicket);
            }
        }, "export-tickets-consigne-$today.csv", ExportService::DEPOSIT_TICKET_HEADER);
    }

}
