<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientBoxType;
use App\Entity\ClientOrderInformation;
use App\Entity\DeliveryMethod;
use App\Entity\Depository;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\ClientRepository;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/referentiel/clients")
 */
class ClientController extends AbstractController {

    /**
     * @Route("/liste", name="clients_list")
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("referential/client/index.html.twig", [
            "new_client" => new Client(),
            "new_client_order_information" => new ClientOrderInformation(),
            "initial_clients" => $this->api($request, $manager)->getContent(),
            "clients_order" => ClientRepository::DEFAULT_DATATABLE_ORDER
        ]);
    }

    /**
     * @Route("/api", name="clients_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $clients = $manager->getRepository(Client::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $data = [];
        foreach ($clients["data"] as $client) {
            $data[] = [
                "id" => $client->getId(),
                "name" => $client->getName(),
                "active" => $client->isActive() ? "Oui" : "Non",
                "address" => $client->getAddress() ?: "-",
                "contact" => FormatHelper::user($client->getContact()),
                "group" => FormatHelper::named($client->getGroup()),
                "linkedMultiSite" => FormatHelper::named($client->getLinkedMultiSite()),
                "multiSite" => $client->isMultiSite() ? "Oui" : "Non",
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true
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
     * @Route("/voir/{client}", name="client_show", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function show(Client $client): Response {
        return $this->render('referential/client/show.html.twig', [
            "client" => $client,
        ]);
    }

    /**
     * @Route("/nouveau", name="client_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $clientRepository = $manager->getRepository(Client::class);

        $content = (object)$request->request->all();
        $depositTicketsClientsIds = explode(",", $content->depositTicketsClients);

        $contact = $manager->getRepository(User::class)->find($content->contact);
        $group = $manager->getRepository(Group::class)->find($content->group);
        $deliveryMethod = isset($content->deliveryMethod) ? $manager->getRepository(DeliveryMethod::class)->find($content->deliveryMethod) : null;
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : null;

        $multiSite = isset($content->linkedMultiSite) ? $clientRepository->find($content->linkedMultiSite) : null;
        $depositTicketsClients = $clientRepository->findBy(["id" => $depositTicketsClientsIds]);

        $existing = $clientRepository->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Ce client existe déjà");
        }

        if ($form->isValid()) {
            $client = (new Client())
                ->setName($content->name)
                ->setAddress($content->address)
                ->setPhoneNumber($content->phoneNumber)
                ->setActive($content->active)
                ->setContact($contact)
                ->setIsMultiSite($content->isMultiSite)
                ->setGroup($group)
                ->setLinkedMultiSite($multiSite)
                ->setDepositTicketClients($depositTicketsClients)
                ->setDepositTicketValidity($content->depositTicketValidity)
                ->setMailNotificationOrderPreparation((bool)$content->mailNotificationOrderPreparation);

            $clientOrderInformation = (new ClientOrderInformation())
                ->setClient($client)
                ->setDeliveryMethod($deliveryMethod)
                ->setDepository($depository)
                ->setDepositoryDistance($content->depositoryDistance ?? null)
                ->setTokenAmount($content->tokenAmount ?? null)
                ->setOrderType($content->orderType ?? null)
                ->setIsClosedParkOrder((bool)$content->isClosedParkOrder ?? null)
                ->setWorkingDayDeliveryRate($content->workingDayDeliveryRate ?? null)
                ->setNonWorkingDayDeliveryRate($content->nonWorkingDayDeliveryRate ?? null)
                ->setServiceCost($content->serviceCost ?? null)
                ->setComment($content->comment ?? null);

            $out = (new Location())
                ->setClient($client)
                ->setActive(true)
                ->setName("{$client->getName()}_sortie")
                ->setDescription("Emplacement de sortie du client {$client->getName()}")
                ->setDeposits(0)
                ->setKiosk(false)
                ->setDeporte(null);

            $client->setOutLocation($out);

            //0 is used to select the client we're creating
            if (in_array(0, $depositTicketsClientsIds)) {
                $depositTicketsClients[] = $client;
            }

            $manager->persist($client);
            $manager->persist($clientOrderInformation);
            $manager->persist($out);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Client créé avec succès",
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
        $clientOrderInformation = $client->getClientOrderInformation();

        return $this->json([
            "submit" => $this->generateUrl("client_edit", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/edit.html.twig", [
                "client" => $client,
                "clientOrderInformation" => $clientOrderInformation
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

        $content = (object)$request->request->all();
        $depositTicketsClientsIds = explode(",", $content->depositTicketsClients);

        $contact = $manager->getRepository(User::class)->find($content->contact);
        $group = $manager->getRepository(Group::class)->find($content->group);
        $multiSite = isset($content->linkedMultiSite) ? $clientRepository->find($content->linkedMultiSite) : null;
        $depositTicketsClients = $clientRepository->findBy(["id" => $depositTicketsClientsIds]);

        $existing = $manager->getRepository(Client::class)->findOneBy(["name" => $content->name]);
        $clientOrderInformation = $manager->getRepository(ClientOrderInformation::class)->findOneBy(['client' => $existing]);

        $deliveryMethod = isset($content->deliveryMethod) ? $manager->getRepository(DeliveryMethod::class)->find($content->deliveryMethod) : $clientOrderInformation->getDeliveryMethod();
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : $clientOrderInformation->getDepository();
        if ($existing !== null && $existing !== $client) {
            $form->addError("name", "Un autre client avec ce nom existe déjà");
        }

        if ($form->isValid()) {
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
                ->setDepositTicketValidity($content->depositTicketValidity)
                ->setMailNotificationOrderPreparation((bool)$content->mailNotificationOrderPreparation);

            if(isset($clientOrderInformation)) {
                $clientOrderInformation
                    ->setDeliveryMethod($deliveryMethod)
                    ->setDepository($depository)
                    ->setDepositoryDistance($content->depositoryDistance ?? $clientOrderInformation->getDepositoryDistance())
                    ->setTokenAmount($content->tokenAmount ?? $clientOrderInformation->getTokenAmount())
                    ->setOrderType($content->orderType ?? $clientOrderInformation->getOrderType())
                    ->setIsClosedParkOrder((bool)$content->isClosedParkOrder ?? $clientOrderInformation->isClosedParkOrder())
                    ->setWorkingDayDeliveryRate($content->workingDayDeliveryRate ?? $clientOrderInformation->getWorkingDayDeliveryRate())
                    ->setNonWorkingDayDeliveryRate($content->nonWorkingDayDeliveryRate ?? $clientOrderInformation->getNonWorkingDayDeliveryRate())
                    ->setServiceCost($content->serviceCost ?? $clientOrderInformation->getServiceCost())
                    ->setComment($content->comment ?? $clientOrderInformation->getComment());
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Client modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{client}", name="client_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function deleteTemplate(Client $client): Response {
        return $this->json([
            "submit" => $this->generateUrl("client_delete", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/delete.html.twig", [
                "client" => $client,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{client}", name="client_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function delete(EntityManagerInterface $manager, Client $client): Response {

        if ($client && (!$client->getBoxRecords()->isEmpty() || !$client->getBoxes()->isEmpty() || $client->getOutLocation())) {
            $client->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Client <strong>{$client->getName()}</strong> désactivé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Le client n'existe pas"
            ]);
        }
    }

    /**
     * @Route("/export", name="clients_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $clients = $manager->getRepository(Client::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $clients) {
            foreach ($clients as $client) {
                $client["active"] = Client::NAMES[$client["active"]];
                $exportService->putLine($output, $client);
            }
        }, "export-clients-$today.csv", ExportService::CLIENT_HEADER);
    }

    /**
     * @Route("/box-types-api", name="box_types_api", options={"expose": true})
     */
    public function boxTypesApi(Request $request, EntityManagerInterface $manager): Response {
        $id = $request->query->get('id');
        $client = $manager->getRepository(Client::class)->find($id);

        return $this->json([
            'success' => true,
            'template' => $this->renderView('referential/client/box_types.html.twig', [
                'client' => $client,
            ]),
            'totalCrateTypePrice' => Stream::from($client->getClientBoxTypes())->map(fn(ClientBoxType $clientBoxType) => $clientBoxType->getQuantity() * (float) $clientBoxType->getCost())->sum()
        ]);
    }

    /**
     * @Route("/add-box-type", name="add_client_box_type", options={"expose": true})
     */
    public function addBoxType(Request $request, EntityManagerInterface $manager) {
        $form = Form::create();

        $content = (object)$request->request->all();

        $client = $manager->getRepository(Client::class)->find($content->client);
        $boxType = $manager->getRepository(BoxType::class)->find($content->type);
        $clientBoxTypes = $client->getClientBoxTypes();

        if ($content->quantity < 1) {
            $form->addError("quantity", "La quantité doit être supérieure ou égale à 1");
        } elseif ($content->price < 0) {
            $form->addError("price", "Le tarif personnalisé doit être supérieur ou égal à 0");
        }

        foreach ($clientBoxTypes as $clientBoxType) {
            if($clientBoxType->getBoxType()->getId() === $boxType->getId()) {
                $form->addError("type", 'Ce type de Box est déjà présent dans le modèle de caisse');
            }
        }

        if($form->isValid()) {

            $name = $boxType->getName();

            $clientBoxType = (new ClientBoxType())
                ->setClient($client)
                ->setBoxType($boxType)
                ->setQuantity((int) $content->quantity)
                ->setCost((float) $content->price);

            $manager->persist($clientBoxType);
            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => "Le type de Box ${name} a bien été ajouté au modèle de caisse"
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/delete-client-box-type", name="delete_client_box_type", options={"expose": true})
     */
    public function deleteClientBoxType(Request $request, EntityManagerInterface $manager): Response {

        $id = $request->query->get('id');
        $clientBoxType = $manager->getRepository(ClientBoxType::class)->find($id);

        if($clientBoxType) {
            $manager->remove($clientBoxType);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Le type de Box <strong>{$clientBoxType->getBoxType()->getName()}</strong> a été supprimé"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "message" => "Une erreur est survenue"
            ]);
        }
    }

    /**
     * @Route("/modifier-client-box-type/template/{clientBoxType}", name="client_box_type_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function clientBoxTypeEditTemplate(ClientBoxType $clientBoxType): Response {
        return $this->json([
            "submit" => $this->generateUrl("client_box_type_edit", ["clientBoxType" => $clientBoxType->getId()]),
            "template" => $this->renderView("referential/client/modal/edit_client_box_type.html.twig", [
                "clientBoxType" => $clientBoxType,
            ])
        ]);
    }

    /**
     * @Route("/modifier-client-box-type/{clientBoxType}", name="client_box_type_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function clientBoxTypeEdit(Request $request, ClientBoxType $clientBoxType, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();

        $client = $manager->getRepository(Client::class)->find($content->client);
        $boxType = $manager->getRepository(BoxType::class)->find($content->type);
        $clientBoxTypes = $client->getClientBoxTypes();

        if ($content->quantity < 1) {
            $form->addError("quantity", "La quantité doit être supérieure ou égale à 1");
        } elseif ($content->price < 0) {
            $form->addError("price", "Le tarif personnalisé doit être supérieur ou égal à 0");
        }

        foreach ($clientBoxTypes as $currentBoxType) {
            if($currentBoxType->getBoxType()->getId() === $boxType->getId()) {
                $form->addError("type", 'Ce type de Box est déjà présent dans le modèle de caisse');
            }
        }

        if($form->isValid()) {
            $name = $boxType->getName();

            $clientBoxType
                ->setBoxType($boxType)
                ->setQuantity((int) $content->quantity)
                ->setCost((float) $content->price);

            $manager->flush();

            return $this->json([
                'success' => true,
                'msg' => "Le type de Box ${name} a bien été modifié"
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/border-recurrence-api", name="order_recurrence_api", options={"expose": true})
     */
    public function orderRecurrenceApi(Request $request, EntityManagerInterface $manager): Response {
        $id = $request->query->get('id');
        $client = $manager->getRepository(Client::class)->find($id);

        return $this->json([
            'success' => true,
            'template' => $this->renderView('referential/client/order_recurrence.html.twig', [
                'clientOrderInformation' => $client->getClientOrderInformation() ?? null,
            ])
        ]);
    }

}
