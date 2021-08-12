<?php

namespace App\Controller\Operation;

use App\Annotation\HasPermission;
use App\Entity\BoxType;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Collect;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\OrderStatusHistory;
use App\Entity\OrderType;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\ClientOrderRepository;
use App\Service\ClientOrderService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/operation/commande-client")
 */
class ClientOrderController extends AbstractController {

    /** @Required */
    public ClientOrderService $service;

    /**
     * @Route("/liste", name="client_orders_list", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS, ROLE::VIEW_ALL_ORDERS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $deliveryMethod = $manager->getRepository(DeliveryMethod::class);
        $orderTypes = $manager->getRepository(OrderType::class);
        $boxTypeRepository = $manager->getRepository(BoxType::class);

        return $this->render("operation/client_order/index.html.twig", [
            "new_client_order" => new ClientOrder(),
            "requester" => $this->getUser(),
            "deliveryMethods" => $deliveryMethod->findBy(["deleted" => false], ["name" => "ASC"]),
            "orderTypes" => $orderTypes->findBy([]),
            "initial_orders" => $this->api($request, $manager)->getContent(),
            "orders_order" => ClientOrderRepository::DEFAULT_DATATABLE_ORDER,
            "starterKit" => $boxTypeRepository->findStarterKit()
        ]);
    }

    /**
     * @Route("/api", name="client_orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(ClientOrder::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());
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
                "linkAction" => $order->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT) ? 'validation' : 'show',
                "linkLabel" => $order->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT) ? 'Enregistrer la commande' : 'Voir les détails'
            ])
            ->toArray();

        $groupedData = [];
        $previousItem = null;
        foreach ($data as $item) {
            if ($previousItem) {
                $groupedData[] = [
                    'id' => $item['id'],
                    'col' => $this->renderView('operation/client_order/order_row.html.twig', ['item1' => $previousItem, 'item2' => $item])
                ];
                $previousItem = null;
            } else {
                $previousItem = $item;
            }
        }
        if ($previousItem) {
            $groupedData[] = [
                'id' => $previousItem['id'],
                'col' => $this->renderView('operation/client_order/order_row.html.twig', ['item1' => $previousItem])
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
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function validateTemplate(ClientOrder $clientOrder): Response {
        return $this->json([
            'template' => $this->renderView("operation/client_order/modal/validation.html.twig", [
                "clientOrder" => $clientOrder
            ])
        ]);
    }

    /**
     * @Route("/{clientOrder}/validate", name="client_order_validation", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function validate(ClientOrder $clientOrder,
                             EntityManagerInterface $entityManager,
                             ClientOrderService $clientOrderService): Response {

        if ($clientOrder->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)) {
            $statusRepository = $entityManager->getRepository(Status::class);
            $validateBoxeatyStatus = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_TO_VALIDATE_BOXEATY]);

            $history = $clientOrderService->updateClientOrderStatus($clientOrder, $validateBoxeatyStatus, $this->getUser());
            $entityManager->persist($history);

            $entityManager->flush();
        }

        return $this->json([
            "success" => true
        ]);
    }

    /**
     * @Route("/new", name="client_order_new", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function new(Request $request,
                        UniqueNumberService $uniqueNumberService,
                        EntityManagerInterface $entityManager,
                        ClientOrderService $clientOrderService): Response {
        $number = $uniqueNumberService->createUniqueNumber($entityManager, ClientOrder::PREFIX_NUMBER, ClientOrder::class);
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
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
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
                'hideEditStatusButton' => $clientOrder->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            ]);
        } else {
            return $form->errors();
        }
        return $this->json($result);
    }

    /**
     * @Route("/supprimer/{clientOrder}", name="client_order_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
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

        $delivery = $entityManager->getRepository(Delivery::class)->findOneBy(['order' => $clientOrder->getId()]);
        $collect = $entityManager->getRepository(Collect::class)->findOneBy(['order' => $clientOrder->getId()]);

        $signature = $delivery->getSignature()->getPath();
        return $this->json([
            "template" => $this->renderView("operation/client_order/modal/show.html.twig", [
                "clientOrder" => $clientOrder,
                "signature" => $signature,
                "delivery" => $delivery,
                "collect" => $collect,
                "deliveryStatus" => Status:: CODE_DELIVERY_DELIVERED,
                "collectStatus" => Status::CODE_COLLECT_FINISHED,
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

        if (!$clientOrder->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
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

        return $this->json([
            "submit" => $this->generateUrl("client_order_edit", ["clientOrder" => $clientOrder->getId()]),
            "template" => $this->renderView("operation/client_order/modal/new.html.twig", [
                "clientOrder" => $clientOrder,
                "orderTypes" => $orderTypeRepository->findBy([]),
                "deliveryMethods" => $deliveryMethodRepository->findBy(["deleted" => false], ["name" => "ASC"]),
                'cartContent' => $cartContent
            ])
        ]);
    }

    /**
     * @Route("/{clientOrder}/edit", name="client_order_edit", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function edit(Request $request,
                         EntityManagerInterface $entityManager,
                         ClientOrderService $clientOrderService,
                         ClientOrder $clientOrder): JsonResponse {
        $status = $clientOrder->getStatus();
        $form = Form::create();

        /** @var User $requester */
        $requester = $this->getUser();

        if (!$clientOrder->isOnStatusCode(Status::CODE_ORDER_TO_VALIDATE_CLIENT)
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
     * @Route("/client-order-history-api", name="client_order_history_api", options={"expose": true})
     */
    public function historyApi(Request $request, EntityManagerInterface $manager): Response {
        $id = $request->query->get('id');
        $clientOrder = $manager->getRepository(ClientOrder::class)->find($id);

        return $this->json([
            'success' => true,
            'template' => $this->renderView('operation/client_order/timeline.html.twig', [
                'clientOrder' => $clientOrder,
            ]),
        ]);
    }

}
