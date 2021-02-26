<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/referentiel/clients")
 */
class ClientController extends AbstractController {

    /**
     * @Route("/liste", name="clients_list")
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function list(): Response {
        return $this->render("referential/client/index.html.twig", [
            "new_client" => new Client(),
        ]);
    }

    /**
     * @Route("/api", name="clients_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $clients = $manager->getRepository(Client::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($clients["data"] as $client) {
            $data[] = [
                "id" => $client->getId(),
                "name" => $client->getName(),
                "active" => $client->isActive() ? "Oui" : "Non",
                "address" => $client->getAddress(),
                "contact" => FormatHelper::user($client->getContact()),
                "group" => FormatHelper::named($client->getGroup()),
                "multiSite" => $client->isMultiSite() ? "Oui" : "Non",
                "linkedMultiSite" => FormatHelper::named($client->getLinkedMultiSite()),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => false
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $clients["total"],
            "recordsFiltered" => $clients["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="client_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $clientRepository = $manager->getRepository(Client::class);

        $content = (object) $request->request->all();
        $contact = $manager->getRepository(User::class)->find($content->contact);
        $group = $manager->getRepository(Group::class)->find($content->group);
        $multiSite = isset($content->linkedMultiSite)? $clientRepository->find($content->linkedMultiSite) : null;
        $depositTicketsClients = $clientRepository->findBy(["id" => $content->depositTicketsClients]);

        $existing = $clientRepository->findOneBy(["name" => $content->name]);
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
                ->setContact($contact)
                ->setIsMultiSite($content->isMultiSite)
                ->setGroup($group)
                ->setLinkedMultiSite($multiSite)
                ->setDepositTicketClients($depositTicketsClients)
                ->setDepositTicketValidity($content->depositTicketValidity);

            //0 is used to select the client we're creating
            if(in_array(0, $content->depositTicketsClients)) {
                $depositTicketsClients[] = $client;
            }

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
     * @Route("/modifier/template/{client}", name="client_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function editTemplate(Client $client): Response {
        return $this->json([
            "submit" => $this->generateUrl("client_edit", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/edit.html.twig", [
                "client" => $client,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{client}", name="client_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Client $client): Response {
        $form = Form::create();

        $clientRepository = $manager->getRepository(Client::class);

        $content = (object) $request->request->all();
        $contact = $manager->getRepository(User::class)->find($content->contact);
        $group = $manager->getRepository(Group::class)->find($content->group);
        $multiSite = isset($content->linkedMultiSite)? $clientRepository->find($content->linkedMultiSite) : null;
        $depositTicketsClients =  $clientRepository->findBy(["id" => explode(",", $content->depositTicketsClients)]);

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
                ->setContact($contact)
                ->setIsMultiSite($content->isMultiSite)
                ->setGroup($group)
                ->setLinkedMultiSite($multiSite)
                ->setDepositTicketClients($depositTicketsClients)
                ->setDepositTicketValidity($content->depositTicketValidity);

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
     * @Route("/export", name="clients_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $users = $manager->getRepository(Client::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $users) {
            foreach ($users as $user) {
                $exportService->putLine($output, $user);
            }
        }, "export-clients-$today.csv", ExportService::CLIENT_HEADER);
    }

}
