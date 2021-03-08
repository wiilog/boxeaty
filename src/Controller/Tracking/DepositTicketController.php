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
    public function list(): Response {
        return $this->render("tracking/deposit_ticket/index.html.twig", [
            "new_deposit_ticket" => (new DepositTicket())
                ->setNumber(StringHelper::random(5)),
        ]);
    }

    /**
     * @Route("/api", name="deposit_tickets_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $depositTickets = $manager->getRepository(DepositTicket::class)
            ->findForDatatable(json_decode($request->getContent(), true), $this->getUser());

        $data = [];
        foreach ($depositTickets["data"] as $depositTicket) {
            $data[] = [
                "id" => $depositTicket->getId(),
                "creationDate" => FormatHelper::datetime($depositTicket->getCreationDate()),
                "kiosk" => FormatHelper::named($depositTicket->getLocation()),
                "validityDate" => FormatHelper::datetime($depositTicket->getValidityDate()),
                "number" => $depositTicket->getNumber() ?? "",
                "useDate" => FormatHelper::datetime($depositTicket->getUseDate()) ?: "Inutilisé",
                "client" => $depositTicket->getLocation() ? FormatHelper::named($depositTicket->getLocation()->getClient()) : "",
                "state" => DepositTicket::NAMES[$depositTicket->getState()] ?? "",
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
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
     * @return Response
     */
    public function new(Request $request,
                        EntityManagerInterface $manager): Response {

        $form = Form::create();

        $depositTicketRepository = $manager->getRepository(DepositTicket::class);
        $content = (object)$request->request->all();
        $kiosk = $manager->getRepository(Location::class)->find($content->location);
        $box = $manager->getRepository(Box::class)->find($content->box);
        $existing = $depositTicketRepository->findOneBy(["number" => $content->number]);
        $alreadyValidTicketOnBoxCount = $depositTicketRepository->count([
            "box" => $box,
            "state" => (DepositTicket::VALID)
        ]);

        if ($alreadyValidTicketOnBoxCount > 0 ) {
            $form->addError("state","Un ticket valide existe déja pour cette boite");
        }

        if ($existing) {
            $form->addError("number", "Ce ticket consigne existe déjà");
        }

        if ($form->isValid()) {
            $depositTicket = new DepositTicket();
            $depositTicket
                ->setBox($box)
                ->setCreationDate(new DateTime())
                ->setLocation($kiosk)
                ->setValidityDate(new DateTime("+{$kiosk->getClient()->getDepositTicketValidity()} month"))
                ->setNumber($content->number)
                ->setState($content->state);

            if ($content->state == DepositTicket::SPENT) {
                $depositTicket->setUseDate(new DateTime());
            }

            $manager->persist($depositTicket);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Ticket consigne <b>{$depositTicket->getNumber()}</b> créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{depositTicket}", name="deposit_ticket_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function editTemplate(DepositTicket $depositTicket): Response {
        return $this->json([
            "submit" => $this->generateUrl("deposit_ticket_edit", ["depositTicket" => $depositTicket->getId()]),
            "template" => $this->renderView("tracking/deposit_ticket/modal/edit.html.twig", [
                "deposit_ticket" => $depositTicket,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{depositTicket}", name="deposit_ticket_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, DepositTicket $depositTicket): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $box = $manager->getRepository(Box::class)->find($content->box);
        $kiosk = $manager->getRepository(Location::class)->find($content->location);
        $existing = $manager->getRepository(DepositTicket::class)->findOneBy(["number" => $content->number]);
        if ($existing !== null && $existing !== $depositTicket) {
            $form->addError("name", "Un autre ticket consigne avec ce numéro existe déjà");
        }

        if ($form->isValid()) {
            $oldState = $depositTicket->getState();
            $depositTicket
                ->setBox($box)
                ->setLocation($kiosk)
                ->setNumber($content->number)
                ->setState($content->state);

            if ($oldState == DepositTicket::VALID
                && $content->state == DepositTicket::SPENT) {
                $depositTicket->setUseDate(new DateTime());
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Ticket consigne modifié avec succès",
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
                "msg" => "Ticket consigne <strong>{$depositTicket->getNumber()}</strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Le ticket consigne n'existe pas"
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
