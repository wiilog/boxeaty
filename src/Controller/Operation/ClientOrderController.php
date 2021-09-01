<?php

namespace App\Controller\Operation;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\DeliveryMethod;
use App\Entity\GlobalSetting;
use App\Entity\OrderType;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\WorkFreeDay;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\ClientOrderRepository;
use App\Service\ClientOrderService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use WiiCommon\Helper\Stream;

/**
 * @Route("/operation/commande-client")
 */
class ClientOrderController extends AbstractController {

    /** @Required */
    public ClientOrderService $service;

    /**
     * @Route("/liste", name="client_orders_list", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $deliveryMethod = $manager->getRepository(DeliveryMethod::class);
        $orderTypeRepository = $manager->getRepository(OrderType::class);
        $boxTypeRepository = $manager->getRepository(BoxType::class);

        return $this->render("operation/client_order/index.html.twig", [
            "new_client_order" => new ClientOrder(),
            "requester" => $this->getUser(),
            "deliveryMethods" => $deliveryMethod->findBy(["deleted" => false], ["name" => "ASC"]),
            "orderTypes" => $orderTypeRepository->findSelectable(),
            "initial_orders" => $this->api($request, $manager)->getContent(),
            "orders_order" => ClientOrderRepository::DEFAULT_DATATABLE_ORDER,
            "starterKit" => $boxTypeRepository->findStarterKit(),
            "workFreeDay" => Stream::from($manager->getRepository(WorkFreeDay::class)->findAll())
                ->map(fn(WorkFreeDay $workFreeDay) => [
                    $workFreeDay->getDay(),
                    $workFreeDay->getMonth()
                ])
                ->toArray()
        ]);
    }

    /**
     * @Route("/api", name="client_orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $params = json_decode($request->getContent(), true) ?? [];
        $orders = $manager->getRepository(ClientOrder::class)->findForDatatable($params, $this->getUser());

        $data = Stream::from($orders["data"])
            ->map(fn (ClientOrder $order) => [
                "id" => $order->getId(),
                "collect" => $order->getCollect(),
                "status" => $order->getStatus(),
                "automatic" => $order->getAutomatic(),
                "client" => $order->getClient(),
                "number" => $order->getNumber(),
                "location" => $order->getClient(),
                "requester" => $order->getRequester(),
                "deliveryMethod" => $order->getDeliveryMethod(),
                "deliveryPrice" => $order->getDeliveryPrice(),
                "servicePrice" => $order->getServicePrice(),
                "cartPrice" => $order->getTotalAmount(),
                "type" => $order->getType(),
                "expectedDelivery" => FormatHelper::dateMonth($order->getExpectedDelivery()),
                "linkAction" => $order->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT) ? "validation" : "show",
                "linkLabel" => $order->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT) ? "Enregistrer la commande" : "Voir les détails"
            ])
            ->toArray();

        $groupedData = [];
        $previousItem = null;
        foreach ($data as $item) {
            if ($previousItem) {
                $groupedData[] = [
                    "id" => $item["id"],
                    "col" => $this->renderView('operation/client_order/order_row.html.twig', [
                        "item1" => $previousItem,
                        "item2" => $item
                    ])
                ];
                $previousItem = null;
            } else {
                $previousItem = $item;
            }
        }
        if ($previousItem) {
            $groupedData[] = [
                "id" => $previousItem["id"],
                "col" => $this->renderView('operation/client_order/order_row.html.twig', [
                    "item1" => $previousItem
                ])
            ];
        }

        return $this->json([
            "data" => $groupedData,
            "recordsTotal" => $orders["total"],
            "recordsFiltered" => $orders["filtered"],
        ]);
    }

    /**
     * @Route("/validate/template/{clientOrder}", name="client_order_validation_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function validateTemplate(ClientOrder $clientOrder): Response {
        /** @var User $requester */
        $requester = $this->getUser();

        if (!$clientOrder->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            || $requester !== $clientOrder->getRequester()) {
            throw new NotFoundHttpException('La commande client est introuvable.');
        }

        return $this->json([
            'template' => $this->renderView("operation/client_order/modal/validation.html.twig", [
                "clientOrder" => $clientOrder
            ])
        ]);
    }

    /**
     * @Route("/{clientOrder}/validate", name="client_order_validation", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function validate(ClientOrder $clientOrder,
                             EntityManagerInterface $entityManager,
                             ClientOrderService $clientOrderService): Response {

        if ($clientOrder->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)) {
            $statusRepository = $entityManager->getRepository(Status::class);
            $globalSettingRepository = $entityManager->getRepository(GlobalSetting::class);

            /** @var User $user */
            $user = $this->getUser();

            $numberDayLimit = $globalSettingRepository->getValue(GlobalSetting::AUTO_VALIDATION_DELAY);
            $quantityLimit = $globalSettingRepository->getValue(GlobalSetting::AUTO_VALIDATION_BOX_QUANTITY);

            if ($numberDayLimit && $quantityLimit) {
                $dayLimit = new DateTime('now + ' . $numberDayLimit . ' days');
                $autoValidationDelay = $clientOrder->getExpectedDelivery();
                $autoValidationQuantity = $clientOrder->getBoxQuantity();

                if ($autoValidationDelay > $dayLimit && $autoValidationQuantity <= $quantityLimit) {
                    $statusCode = Status::CODE_ORDER_PLANNED;
                    $clientOrder->setAutomatic(true);
                    $clientOrder->setValidatedAt(new DateTime());
                    $clientOrder->setValidator($this->getUser());
                }
            }

            $status = $statusRepository->findOneBy(['code' => $statusCode ?? Status::CODE_ORDER_TO_VALIDATE_BOXEATY]);
            $history = $clientOrderService->updateClientOrderStatus($clientOrder, $status, $user);

            $entityManager->persist($history);
            $entityManager->flush();
        }

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/new", name="client_order_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function new(Request $request,
                        UniqueNumberService $uniqueNumberService,
                        EntityManagerInterface $entityManager,
                        ClientOrderService $clientOrderService): Response {
        $number = $uniqueNumberService->createUniqueNumber(ClientOrder::class);
        $now = new DateTime('now');

        $clientOrder = new ClientOrder();
        $form = Form::create();
        $clientOrderService->updateClientOrder($request, $entityManager, $form, $clientOrder);

        if ($form->isValid()) {
            /** @var User $requester */
            $requester = $this->getUser();
            $statusRepository = $entityManager->getRepository(Status::class);
            $status = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_TO_VALIDATE_CLIENT]);
            $history = $clientOrderService->updateClientOrderStatus($clientOrder, $status, $requester);
            $clientOrder
                ->setNumber($number)
                ->setCreatedAt($now)
                ->setAutomatic(false)
                ->setValidatedAt(null)
                ->setValidator(null)
                ->setDeliveryRound(null)
                ->setRequester($requester);

            $entityManager->persist($history);
            $entityManager->persist($clientOrder);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "clientOrderId" => $clientOrder->getId(),
                "validationTemplate" => $this->renderView("operation/client_order/modal/validation.html.twig", [
                    "clientOrder" => $clientOrder
                ]),
            ]);
        }

        return $form->errors();
    }

    /**
     * @Route("/supprimer/template/{clientOrder}", name="client_order_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS, Role::DELETE_CLIENT_ORDERS)
     */
    public function deleteTemplate(ClientOrder $clientOrder): Response {
        return $this->json([
            "submit" => $this->generateUrl("client_order_delete", ["clientOrder" => $clientOrder->getId()]),
            "template" => $this->renderView("operation/client_order/modal/delete.html.twig", [
                "clientOrder" => $clientOrder,
            ])
        ]);
    }

    /**
     * @Route("/status/template/{clientOrder}", name="client_order_edit_status_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function editStatusTemplate(ClientOrder $clientOrder): Response {
        return $this->json([
            "submit" => $this->generateUrl("client_order_edit_status", ["clientOrder" => $clientOrder->getId()]),
            "template" => $this->renderView("operation/client_order/modal/edit_status.html.twig", [
                "clientOrder" => $clientOrder,
            ])
        ]);
    }

    /**
     * @Route("/status/{clientOrder}", name="client_order_edit_status", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function editStatus(ClientOrder $clientOrder,
                               Request $request,
                               EntityManagerInterface $entityManager,
                               ClientOrderService $clientOrderService): Response {

        $form = Form::create();

        $content = (object)$request->request->all();
        $statusRepository = $entityManager->getRepository(Status::class);
        $status = $statusRepository->findOneBy(['id' => $content->status]);

        if ($form->isValid()) {
            $history = $clientOrderService->updateClientOrderStatus($clientOrder, $status, $this->getUser());
            $history->setJustification($content->justification);
            $entityManager->persist($history);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                'hideEditStatusButton' => $clientOrder->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/{clientOrder}", name="client_order_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS, Role::DELETE_CLIENT_ORDERS)
     */
    public function delete(EntityManagerInterface $entityManager, ClientOrder $clientOrder): Response {
        $lines = $clientOrder->getLines();

        foreach ($lines as $line) {
            $entityManager->remove($line);
        }

        $entityManager->remove($clientOrder);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "message" => "Commande client <strong>{$clientOrder->getNumber()}</strong> supprimée avec succès"
        ]);
    }

    /**
     * @Route("/voir/template/{clientOrder}", name="client_order_show_template", options={"expose": true})
     */
    public function showTemplate(EntityManagerInterface $entityManager, ClientOrder $clientOrder): Response {
        $roles = $entityManager->getRepository(Role::class)->findBy(["active" => true]);

        return $this->json([
            "template" => $this->renderView("operation/client_order/modal/show.html.twig", [
                "clientOrder" => $clientOrder,
                "roles" => $roles,
            ])
        ]);
    }

    /**
     * @Route("/{clientOrder}/edit/template", name="client_order_edit_template", options={"expose": true})
     */
    public function editTemplate(ClientOrder $clientOrder,
                                 EntityManagerInterface $entityManager): Response {
        /** @var User $requester */
        $requester = $this->getUser();

        if (!$clientOrder->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            || $requester !== $clientOrder->getRequester()) {
            throw new NotFoundHttpException('La commande client est introuvable.');
        }

        $orderTypeRepository = $entityManager->getRepository(OrderType::class);
        $deliveryMethodRepository = $entityManager->getRepository(DeliveryMethod::class);

        $cartContent = $clientOrder->getLines()
            ->map(fn(ClientOrderLine $line) => [
                'id' => $line->getBoxType()->getId(),
                'unitPrice' => $line->getUnitPrice(),
                'quantity' => $line->getQuantity(),
                'name' => $line->getBoxType()->getName(),
                'volume' => $line->getBoxType()->getVolume(),
                'image' => $line->getBoxType()->getImage()
                    ? $line->getBoxType()->getImage()->getPath()
                    : null
            ])
            ->toArray();

        $client = $clientOrder->getClient();
        if ($client) {
            $clientOrderInformation = $client->getClientOrderInformation();
            if ($clientOrderInformation) {
                $deliveryMethodId = $clientOrderInformation->getDeliveryMethod()
                    ? $clientOrderInformation->getDeliveryMethod()->getId()
                    : null;
                $workingRate = $clientOrderInformation->getWorkingDayDeliveryRate();
                $nonWorkingRate = $clientOrderInformation->getNonWorkingDayDeliveryRate();
                $serviceCost = $clientOrderInformation->getServiceCost();
            }
            $initialClient = [
                'id' => $client->getId(),
                'text' => $client->getName(),
                'address' => $client->getAddress(),
                'deliveryMethod' => $deliveryMethodId ?? null,
                'workingRate' => $workingRate ?? null,
                'nonWorkingRate' => $nonWorkingRate ?? null,
                'serviceCost' => $serviceCost ?? null
            ];
        }
        return $this->json([
            "submit" => $this->generateUrl("client_order_edit", ["clientOrder" => $clientOrder->getId()]),
            "template" => $this->renderView("operation/client_order/modal/new.html.twig", [
                "clientOrder" => $clientOrder,
                "initialClient" => $initialClient ?? null,
                "orderTypes" => $orderTypeRepository->findSelectable(),
                "deliveryMethods" => $deliveryMethodRepository->findBy(["deleted" => false], ["name" => "ASC"]),
                'cartContent' => $cartContent,
                "workFreeDay" => Stream::from($entityManager->getRepository(WorkFreeDay::class)->findAll())
                    ->map(fn(WorkFreeDay $workFreeDay) => [
                        $workFreeDay->getDay(),
                        $workFreeDay->getMonth()
                    ])
                    ->toArray()
            ])
        ]);
    }

    /**
     * @Route("/{clientOrder}/edit", name="client_order_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         ClientOrderService $clientOrderService,
                         ClientOrder $clientOrder): JsonResponse {
        $form = Form::create();

        /** @var User $requester */
        $requester = $this->getUser();

        if (!$clientOrder->hasStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            || $requester !== $clientOrder->getRequester()) {
            $form->addError('La commande client ne peut pas être modifiée.');
        }

        if ($form->isValid()) {
            $clientOrderService->updateClientOrder($request, $entityManager, $form, $clientOrder);
        }

        // attention: validity can change in updateClientOrder
        if ($form->isValid()) {
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "clientOrderId" => $clientOrder->getId(),
                "validationTemplate" => $this->renderView("operation/client_order/modal/validation.html.twig", [
                    "clientOrder" => $clientOrder
                ]),
            ]);
        }

        return $form->errors();
    }

    /**
     * @Route("/{clientOrder}/client-order-history-api", name="client_order_history_api", options={"expose": true})
     */
    public function historyApi(ClientOrder $clientOrder): Response {
        return $this->json([
            "success" => true,
            "template" => $this->renderView('operation/client_order/timeline.html.twig', [
                "clientOrder" => $clientOrder,
            ]),
        ]);
    }

    /**
     * @Route("/crates-amount", name="get_crates_amount", options={"expose": true})
     */
    public function cartSplitting(Request                $request,
                                  ClientOrderService     $clientOrderService,
                                  EntityManagerInterface $entityManager): Response {

        $clientRepository = $entityManager->getRepository(Client::class);

        $clientId = $request->query->get('client');
        $client = $clientRepository->find($clientId);
        $cart = $request->query->get('cart') ?: [];

        if (!empty($client) && !empty($cart)) {
            $cartSplitting = $clientOrderService->getCartSplitting($entityManager, $client, $cart);
            return $this->json([
                'success' => true,
                'cratesAmount' => count($cartSplitting)
            ]);
        }

        throw new InvalidParameterException('Invalid params.');
    }
}
