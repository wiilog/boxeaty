<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrderInformation;
use App\Entity\CratePatternLine;
use App\Entity\DeliveryMethod;
use App\Entity\Depository;
use App\Entity\GlobalSetting;
use App\Entity\Group;
use App\Entity\Location;
use App\Entity\OrderRecurrence;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\ClientRepository;
use App\Service\ClientService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/referentiel/clients")
 */
class ClientController extends AbstractController {

    /** @Required */
    public ClientService $service;

    private function canSee(?Group $group) {
        return !$this->getUser()->getRole()->isAllowEditOwnGroupOnly() ||
            $this->getUser()->getGroups()->contains($group) ||
            $this->getUser()->getGroups()->isEmpty();
    }

    private function cantSeeResponse(): Response {
        return $this->json([
            "success" => false,
            "message" => "Vous n'avez pas le droit de voir ou modifier ce client",
        ]);
    }

    /**
     * @Route("/liste", name="clients_list", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $paymentModes = $manager->getRepository(GlobalSetting::class)->getValue(GlobalSetting::PAYMENT_MODES);

        return $this->render("referential/client/index.html.twig", [
            "new_client" => new Client(),
            "new_client_order_information" => new ClientOrderInformation(),
            "initial_clients" => $this->api($request, $manager)->getContent(),
            "clients_order" => ClientRepository::DEFAULT_DATATABLE_ORDER,
            "paymentModes" => explode(',', $paymentModes),
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
        foreach($clients["data"] as $client) {
            $data[] = [
                "id" => $client->getId(),
                "name" => $client->getName(),
                "active" => $client->isActive() ? "Oui" : "Non",
                "address" => $client->getAddress() ?: "-",
                "contact" => FormatHelper::user($client->getContact()),
                "group" => FormatHelper::named($client->getGroup()),
                "linkedMultiSite" => FormatHelper::named($client->getLinkedMultiSite()),
                "isMultiSite" => $client->isMultiSite() ? "Oui" : "Non",
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
        if(!$this->canSee($client->getGroup())) {
            throw new AccessDeniedHttpException("Vous n'avez pas le droit de voir ou modifier ce client");
        }

        return $this->render('referential/client/show.html.twig', [
            "client" => $client,
        ]);
    }

    /**
     * @Route("/nouveau", name="client_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function new(Request $request, EntityManagerInterface $manager, ClientService $service): Response {
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
        if($existing) {
            $form->addError("name", "Ce client existe déjà");
        }

        $client = new Client();

        if(!$service->updateCoordinates($client, $content->address)) {
            $form->addError("address", "Adresse inconnue");
        }

        if($form->isValid()) {
            $client = $client
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
                ->setMailNotificationOrderPreparation((bool)$content->mailNotificationOrderPreparation)
                ->setPaymentModes($content->paymentModes ?? null);

            $clientOrderInformation = (new ClientOrderInformation())
                ->setClient($client)
                ->setDeliveryMethod($deliveryMethod)
                ->setDepository($depository)
                ->setDepositoryDistance($content->depositoryDistance ?? null)
                ->setTokenAmount($content->tokenAmount ?? null)
                ->setOrderTypes(isset($content->orderType) ? explode(',', $content->orderType) : [])
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
                ->setOffset(null);

            $client->setOutLocation($out);
            $client->setClientOrderInformation($clientOrderInformation);

            //0 is used to select the client we're creating
            if(in_array(0, $depositTicketsClientsIds)) {
                $depositTicketsClients[] = $client;
            }

            $this->service->recalculateMonthlyPrice($client);

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
    public function editTemplate(EntityManagerInterface $manager, Client $client): Response {
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        $clientOrderInformation = $client->getClientOrderInformation();
        $paymentModes = $manager->getRepository(GlobalSetting::class)->getValue(GlobalSetting::PAYMENT_MODES);

        return $this->json([
            "submit" => $this->generateUrl("client_edit", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/edit.html.twig", [
                "client" => $client,
                "clientOrderInformation" => $clientOrderInformation,
                "paymentModes" => explode(',', $paymentModes),
            ]),
        ]);
    }

    /**
     * @Route("/modifier/{client}", name="client_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, ClientService $service, Client $client): Response {
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        $form = Form::create();

        $clientRepository = $manager->getRepository(Client::class);

        $content = (object)$request->request->all();
        $depositTicketsClientsIds = explode(",", $content->depositTicketsClients);

        $contact = $manager->getRepository(User::class)->find($content->contact);
        $group = $manager->getRepository(Group::class)->find($content->group);
        $multiSite = isset($content->linkedMultiSite) ? $clientRepository->find($content->linkedMultiSite) : null;
        $depositTicketsClients = $clientRepository->findBy(["id" => $depositTicketsClientsIds]);

        $existing = $manager->getRepository(Client::class)->findOneBy(["name" => $content->name]);
        if(!$client->getClientOrderInformation()) {
            $client->setClientOrderInformation(new ClientOrderInformation());
        }
        $clientOrderInformation = $client->getClientOrderInformation();

        $deliveryMethod = isset($content->deliveryMethod) ? $manager->getRepository(DeliveryMethod::class)->find($content->deliveryMethod) : null;
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : null;
        if($existing !== null && $existing !== $client) {
            $form->addError("name", "Un autre client avec ce nom existe déjà");
        }

        if(!$service->updateCoordinates($client, $content->address)) {
            $form->addError("address", "Adresse inconnue");
        }

        if($form->isValid()) {
            if($client->getName() === Client::BOXEATY) {
                $client->setActive(true);
            } else {
                $client->setName($content->name)
                    ->setActive($content->active);
            }

            $client
                ->setName($content->name)
                ->setAddress($content->address)
                ->setPhoneNumber($content->phoneNumber)
                ->setContact($contact)
                ->setIsMultiSite($content->isMultiSite)
                ->setGroup($group)
                ->setLinkedMultiSite($multiSite)
                ->setDepositTicketClients($depositTicketsClients)
                ->setDepositTicketValidity($content->depositTicketValidity)
                ->setMailNotificationOrderPreparation((bool)$content->mailNotificationOrderPreparation)
                ->setPaymentModes($content->paymentModes ?? null);

            if(isset($clientOrderInformation)) {
                $clientOrderInformation
                    ->setDeliveryMethod($deliveryMethod)
                    ->setDepository($depository)
                    ->setDepositoryDistance($content->depositoryDistance ?? $clientOrderInformation->getDepositoryDistance())
                    ->setTokenAmount($content->tokenAmount ?? $clientOrderInformation->getTokenAmount())
                    ->setOrderTypes(isset($content->orderType) ? explode(',', $content->orderType) : [])
                    ->setIsClosedParkOrder((bool)$content->isClosedParkOrder ?? $clientOrderInformation->isClosedParkOrder())
                    ->setWorkingDayDeliveryRate($content->workingDayDeliveryRate ?? $clientOrderInformation->getWorkingDayDeliveryRate())
                    ->setNonWorkingDayDeliveryRate($content->nonWorkingDayDeliveryRate ?? $clientOrderInformation->getNonWorkingDayDeliveryRate())
                    ->setServiceCost($content->serviceCost ?? $clientOrderInformation->getServiceCost())
                    ->setComment($content->comment ?? $clientOrderInformation->getComment());
            } else {
                $clientOrderInformation = (new ClientOrderInformation())
                    ->setClient($client)
                    ->setDeliveryMethod($deliveryMethod)
                    ->setDepository($depository)
                    ->setDepositoryDistance($content->depositoryDistance ?? null)
                    ->setTokenAmount($content->tokenAmount ?? null)
                    ->setOrderTypes(isset($content->orderType) ? explode(',', $content->orderType) : [])
                    ->setIsClosedParkOrder((bool)$content->isClosedParkOrder ?? null)
                    ->setWorkingDayDeliveryRate($content->workingDayDeliveryRate ?? null)
                    ->setNonWorkingDayDeliveryRate($content->nonWorkingDayDeliveryRate ?? null)
                    ->setServiceCost($content->serviceCost ?? null)
                    ->setComment($content->comment ?? null);

                $manager->persist($clientOrderInformation);
                $client->setClientOrderInformation($clientOrderInformation);
            }

            $this->service->recalculateMonthlyPrice($client);

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
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "submit" => $this->generateUrl("client_delete", ["client" => $client->getId()]),
            "template" => $this->renderView("referential/client/modal/delete.html.twig", [
                "client" => $client,
            ]),
        ]);
    }

    /**
     * @Route("/supprimer/{client}", name="client_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function delete(EntityManagerInterface $manager, Client $client): Response {
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        if($client && (!$client->getBoxRecords()->isEmpty() || !$client->getBoxes()->isEmpty() || $client->getOutLocation())) {
            $client->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Client <strong>{$client->getName()}</strong> désactivé avec succès",
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Le client n'existe pas",
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
            foreach($clients as $client) {
                $client["active"] = Client::NAMES[$client["active"]];
                $exportService->putLine($output, $client);
            }
        }, "export-clients-$today.csv", ExportService::CLIENT_HEADER);
    }

    /**
     * @Route("/crate-pattern-lines-api", name="crate_pattern_lines_api", options={"expose": true})
     */
    public function boxTypesApi(Request $request, EntityManagerInterface $manager): Response {
        $client = $manager->getRepository(Client::class)->find($request->query->get("id"));
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "success" => true,
            "template" => $this->renderView('referential/client/crate_pattern_lines.html.twig', [
                "client" => $client,
            ]),
            "total_crate_type_price" => FormatHelper::price($client->getCratePatternAmount()),
        ]);
    }

    /**
     * @Route("/{client}/crate-pattern-lines", name="crate_pattern_lines", options={"expose": true})
     */
    public function getBoxTypes(Client $client): Response {
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "box_types" => $client->getCratePatternLines()
                ->map(fn(CratePatternLine $cratePatternLine) => [
                    "id" => $cratePatternLine->getBoxType()->getId(),
                    "unitPrice" => $cratePatternLine->getUnitPrice(),
                    "quantity" => $cratePatternLine->getQuantity(),
                    "name" => $cratePatternLine->getBoxType()->getName(),
                    "volume" => $cratePatternLine->getBoxType()->getVolume(),
                    "image" => $cratePatternLine->getBoxType()->getImage()
                        ? $cratePatternLine->getBoxType()->getImage()->getPath()
                        : null,
                ])
                ->toArray(),
        ]);
    }

    /**
     * @Route("/add-crate-pattern-line", name="add_crate_pattern_line", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function addCratePatternLine(Request $request, EntityManagerInterface $manager) {
        $form = Form::create();

        $content = (object)$request->request->all();

        $client = $manager->getRepository(Client::class)->find($content->client);
        $boxType = $manager->getRepository(BoxType::class)->find($content->type);

        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        if($content->quantity < 1) {
            $form->addError("quantity", "La quantité doit être supérieure ou égale à 1");
        } else if(isset($content->customPrice) && $content->customPrice < 0) {
            $form->addError("customPrice", "Le tarif personnalisé doit être supérieur ou égal à 0");
        }

        $doesntContain = Stream::from($client->getCratePatternLines())
            ->filter(fn(CratePatternLine $line) => $line->getBoxType()->getId() === $boxType->getId())
            ->isEmpty();

        if(!$doesntContain) {
            $form->addError("type", "Ce type de Box est déjà présent dans le modèle de caisse");
        }

        if($form->isValid()) {
            $cratePatternLine = (new CratePatternLine())
                ->setClient($client)
                ->setBoxType($boxType)
                ->setQuantity((int)$content->quantity)
                ->setCustomUnitPrice(isset($content->customPrice) ? (float)$content->customPrice : null);

            $this->service->recalculateMonthlyPrice($cratePatternLine);

            $manager->persist($cratePatternLine);
            $manager->flush();

            return $this->json([
                'success' => true,
                "message" => "Le type de Box {$boxType->getName()} a bien été ajouté au contenu de la commande",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier-crate-pattern-line/template/{cratePatternLine}", name="crate_pattern_line_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function cratePatternLineEditTemplate(CratePatternLine $cratePatternLine): Response {
        if(!$this->canSee($cratePatternLine->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "submit" => $this->generateUrl("crate_pattern_line_edit", ["cratePatternLine" => $cratePatternLine->getId()]),
            "template" => $this->renderView("referential/client/modal/edit_crate_pattern_line.html.twig", [
                "cratePatternLine" => $cratePatternLine,
            ]),
        ]);
    }

    /**
     * @Route("/modifier-modele-caisse/{cratePatternLine}", name="crate_pattern_line_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function cratePatternLineEdit(Request                $request,
                                         CratePatternLine       $cratePatternLine,
                                         EntityManagerInterface $manager): Response {
        if(!$this->canSee($cratePatternLine->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        $form = Form::create();
        $content = (object)$request->request->all();

        if($content->quantity < 1) {
            $form->addError("quantity", "La quantité doit être supérieure ou égale à 1");
        } else if(isset($content->customPrice) && $content->customPrice < 0) {
            $form->addError("customPrice", "Le tarif personnalisé doit être supérieur ou égal à 0");
        }

        if($form->isValid()) {
            $cratePatternLine
                ->setQuantity((int)$content->quantity)
                ->setCustomUnitPrice(isset($content->customPrice) ? (float)$content->customPrice : null);

            $this->service->recalculateMonthlyPrice($cratePatternLine);

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Le type de Box {$cratePatternLine->getBoxType()->getName()} a bien été modifié",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/order-recurrence-api", name="order_recurrence_api", options={"expose": true})
     */
    public function orderRecurrenceApi(Request $request, EntityManagerInterface $manager): Response {
        $client = $manager->getRepository(Client::class)->find($request->query->get('id'));
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        $clientOrderInformation = $client->getClientOrderInformation() ?? null;
        $orderRecurrence = $clientOrderInformation ? $clientOrderInformation->getOrderRecurrence() : null;

        return $this->json([
            "success" => true,
            "template" => $this->renderView('referential/client/order_recurrence.html.twig', [
                "client" => $client,
                "recurrence" => $orderRecurrence,
            ]),
            "orderRecurrencePrice" => $orderRecurrence ? $orderRecurrence->getMonthlyPrice() : 0,
        ]);
    }

    /**
     * @Route("/crate-pattern-line-delete/template/{cratePatternLine}", name="crate_pattern_line_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function cratePatternLineDeleteTemplate(CratePatternLine $cratePatternLine): Response {
        if(!$this->canSee($cratePatternLine->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "submit" => $this->generateUrl("crate_pattern_line_delete", ["cratePatternLine" => $cratePatternLine->getId()]),
            "template" => $this->renderView("referential/client/modal/delete_crate_pattern_line.html.twig", [
                "cratePatternLine" => $cratePatternLine,
            ]),
        ]);
    }

    /**
     * @Route("/crate-pattern-line-delete/{cratePatternLine}", name="crate_pattern_line_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function cratePatternLineDelete(EntityManagerInterface $manager, CratePatternLine $cratePatternLine): Response {
        if(!$this->canSee($cratePatternLine->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        $client = $cratePatternLine->getClient();
        $cratePatternLine->setClient(null);

        $this->service->recalculateMonthlyPrice($client);

        $manager->remove($cratePatternLine);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Le type de Box <strong>{$cratePatternLine->getBoxType()->getName()}</strong> a été supprimé",
        ]);
    }

    /**
     * @Route("/modifier-recurrence-commande/{recurrence}/template", name="order_recurrence_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function orderRecurrenceEditTemplate(OrderRecurrence $recurrence): Response {
        if(!$this->canSee($recurrence->getClientOrderInformation()->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        return $this->json([
            "submit" => $this->generateUrl("order_recurrence_edit", ["recurrence" => $recurrence->getId()]),
            "template" => $this->renderView("referential/client/modal/edit_order_recurrence.html.twig", [
                "orderRecurrence" => $recurrence,
            ]),
        ]);
    }

    /**
     * @Route("/modifier-recurrence-commande/{recurrence}", name="order_recurrence_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function orderRecurrenceEdit(Request $request, EntityManagerInterface $manager, OrderRecurrence $recurrence): Response {
        if(!$this->canSee($recurrence->getClientOrderInformation()->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        $form = Form::create();
        $content = (object)$request->request->all();

        if($form->isValid()) {
            $recurrence
                ->setPeriod($content->period)
                ->setCrateAmount($content->crateAmount)
                ->setDay($content->day)
                ->setStart(new DateTime($content->start))
                ->setEnd(new DateTime($content->end))
                ->setDeliveryFlatRate($content->deliveryFlatRate)
                ->setServiceFlatRate($content->serviceFlatRate);

            $this->service->recalculateMonthlyPrice($recurrence);

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "La récurrence de commande a bien été modifiée",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/ajouter-recurrence-commande", name="add_order_recurrence", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function orderRecurrenceAdd(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $client = $manager->getRepository(Client::class)->find($content->client);
        if(!$this->canSee($client->getGroup())) {
            return $this->cantSeeResponse();
        }

        if($content->period < 0) {
            $form->addError("period", "La période doit être supérieure ou égale à 0");
        } else if($content->crateAmount < 0) {
            $form->addError("crateAmount", "Le nombre de caisses doit être supérieur ou égal à 0");
        } else if(new DateTime($content->end) < new DateTime($content->start)) {
            $form->addError("end", "La date de fin doit être supérieure à la date de début");
        } else if($content->deliveryFlatRate < 0) {
            $form->addError("deliveryFlatRate", "Le forfait livraison commande libre doit être supérieur ou égal à 0");
        } else if($content->serviceFlatRate < 0) {
            $form->addError("serviceFlatRate", "Le forfait de service à la commande libre doit être supérieur ou égal à 0");
        }

        if($form->isValid()) {
            $recurrence = (new OrderRecurrence())
                ->setClientOrderInformation($client->getClientOrderInformation())
                ->setPeriod($content->period)
                ->setCrateAmount($content->crateAmount)
                ->setDay($content->day)
                ->setStart(new DateTime($content->start))
                ->setEnd(new DateTime($content->end))
                ->setDeliveryFlatRate($content->deliveryFlatRate)
                ->setServiceFlatRate($content->serviceFlatRate)
                ->setLastEdit(new DateTime());

            $this->service->recalculateMonthlyPrice($recurrence);

            $manager->persist($recurrence);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "La récurrence de commande a bien été ajoutée",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer-recurrence-commande/{orderRecurrence}", name="order_recurrence_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENTS)
     */
    public function orderRecurrenceDelete(EntityManagerInterface $manager, OrderRecurrence $orderRecurrence): Response {
        if(!$this->canSee($orderRecurrence->getClientOrderInformation()->getClient()->getGroup())) {
            return $this->cantSeeResponse();
        }

        $clientOrderInformation = $manager->getRepository(ClientOrderInformation::class)->findOneBy(["orderRecurrence" => $orderRecurrence]);

        $clientOrderInformation->setOrderRecurrence(null);
        $manager->remove($orderRecurrence);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "La récurrence a bien été supprimée",
        ]);
    }

}
