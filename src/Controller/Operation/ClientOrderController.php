<?php

namespace App\Controller\Operation;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\DeliveryMethod;
use App\Entity\OrderType;
use App\Entity\Role;
use App\Entity\Status;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\ClientOrderRepository;
use App\Service\ClientOrderService;
use App\Service\CounterOrderService;
use App\Service\UniqueNumberService;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/operation/commande-client")
 */
class ClientOrderController extends AbstractController {

    /** @Required */
    public CounterOrderService $service;

    /**
     * @Route("/liste", name="client_orders_list", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS, ROLE::VIEW_ALL_ORDERS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $deliveryMethod = $manager->getRepository(DeliveryMethod::class);
        $orderTypes = $manager->getRepository(OrderType::class);
        $now = date('Y-m-d');
        return $this->render("operation/client_order/index.html.twig", [
            "new_client_order" => new ClientOrder(),
            "now" => date('Y-m-d', strtotime($now.'+ 1 days')),
            "requester" => $this->getUser(),
            "deliveryMethods" => $deliveryMethod->findBy([]),
            "orderTypes"=> $orderTypes->findBy([]),
            "initial_orders" => $this->api($request, $manager)->getContent(),
            "orders_order" => ClientOrderRepository::DEFAULT_DATATABLE_ORDER
        ]);
    }

    /**
     * @Route("/api", name="client_orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(ClientOrder::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());
        $data = [];
        /** @var ClientOrder $order */
        foreach ($orders["data"] as $order) {
            $data[] = [
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
                "type" => $order->getType(),
                "expectedDelivery" => FormatHelper::dateMonth($order->getExpectedDelivery()),
            ];
        }
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
     * @Route("/validate/template", name="client_order_validate_template", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function validateTemplate(Request $request,
                        EntityManagerInterface $entityManager,
                         ClientOrderService $clientOrderService): Response {

        return $this->json([
            "submit" => $this->generateUrl("client_order_new", [
                "clientOrder" => $clientOrder ->getId(),
                ]),
            "template" => $this->renderView("operation/client_order/modal/validation.html.twig", [
                "clientOrder" => $clientOrder,
            ])
        ]);
    }

    /**
     * @Route("/validate/{clientOrder}", name="client_order_validate", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function validate(){

    }

    /**
     * @Route("/new", name="client_order_new", options={"expose": true})
     * @HasPermission(Role::CREATE_CLIENT_ORDERS)
     */
    public function new(Request $request,
                        UniqueNumberService $uniqueNumberService,
                        EntityManagerInterface $entityManager,
                        ClientOrderService $clientOrderService): Response {


        $form = Form::create();
        $content = (object)$request->request->all();
        $statusRepository = $entityManager->getRepository(Status::class);
        $typeRepository = $entityManager->getRepository(OrderType::class);
        $clientRepository = $entityManager->getRepository(Client::class);
        $deliveryMethodRepository = $entityManager->getRepository(DeliveryMethod::class);

        $status = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_TO_VALIDATE]);
        $requester = $this->getUser();

        $deliveryMethodId = $content->deliveryMethod ?? null;
        $deliveryMethod = $deliveryMethodId
            ? $deliveryMethodRepository->find($deliveryMethodId)
            : null;
        if (!$deliveryMethod) {
            $form->addError('Vous devez sélectionner au moins un moyen de transport');
        }

        $typeId = $content->type ?? null;
        $type = $typeId
            ? $typeRepository->find($typeId)
            : null;
        if (!$type) {
            $form->addError('Vous devez sélectionner au moins un type de commande');
        }

        $clientId = $content->client ?? null;
        $client = $clientId
            ? $clientRepository->find($clientId)
            : null;
        if (!$client) {
            $form->addError('client', 'Ce champ est requis');
        }

        $information = $client->getClientOrderInformation();
        $expectedDelivery = DateTime::createFromFormat('Y-m-d\TH:i', $content->date ?? null);

        if (isset($information)) {
            // check if it's the weekend
            $deliveryRate = !in_array($expectedDelivery->format('N'), [6, 7])
                ? $information->getWorkingDayDeliveryRate()
                : $information->getNonWorkingDayDeliveryRate();
            $serviceCost = $information->getServiceCost();
        } else {
            $deliveryRate = null;
            $serviceCost = null;
        }
        $number = $uniqueNumberService->createUniqueNumber($entityManager, ClientOrder::PREFIX_NUMBER, ClientOrder::class);
        $now = new DateTime('now');
        if ($type && $type->getCode() == OrderType::AUTONOMOUS_MANAGEMENT) {
            $collectRequired = (bool) ($content->collectRequired ?? false);
            if($collectRequired){
                if (!isset($content->cratesAmountToCollect)
                    || $content->cratesAmountToCollect < 1) {
                    $form->addError('cratesAmountToCollect', 'Le nombre de caisses à collecter est invalide');
                }
            }
        }

        $handledCartLines = $clientOrderService->handleCartLines($entityManager, $form, $content);
        if ($form->isValid()) {
            $clientOrder = (new ClientOrder())
                ->setNumber($number)
                ->setCreatedAt($now)
                ->setExpectedDelivery($expectedDelivery)
                ->setClient($client)
                ->setCratesAmountToCollect($content->cratesAmountToCollect ?? null)
                ->setCollectRequired($collectRequired ?? false)
                ->setAutomatic(false)
                ->setDeliveryPrice($deliveryRate)
                ->setServicePrice($serviceCost)
                ->setValidatedAt(null)
                ->setComment($content->comment ?? null)
                ->setType($type)
                ->setStatus($status)
                ->setDeliveryMethod($deliveryMethod)
                ->setRequester($requester)
                ->setValidator(null)
                ->setDeliveryRound(null);

            foreach ($handledCartLines as $cartLine) {
                $clientOrderLine = new ClientOrderLine();
                $clientOrderLine
                    ->setBoxType($cartLine['boxType'])
                    ->setQuantity($cartLine['quantity'])
                    ->setCustomUnitPrice($cartLine['customUnitPrice'])
                    ->setClientOrder($clientOrder);

                $entityManager->persist($clientOrderLine);
            }

            $entityManager->persist($clientOrder);
            $entityManager->flush();

            return $this->json([
                "success" => true,
                "validationTemplate" => $this->renderView("operation/client_order/modal/validation.html.twig", [
                    "clientOrder" => $clientOrder,
                    "expectedDelivery" => FormatHelper::dateMonth($expectedDelivery),
                ]),
            ]);
        } else {
            return $form->errors();
        }

        return $this->json($result);
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
     * @Route("/supprimer/{clientOrder}", name="client_order_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS)
     */
    public function delete(EntityManagerInterface $manager, ClientOrder $clientOrder): Response {
        $manager->remove($clientOrder);
        $manager->flush();

        $clientOrder = $clientOrderService->createClientOrder($this->getUser(), $entityManager, $request);
        return $this->json([
            "success" => true,
            "message" => "Commande client <strong>{$clientOrder->getNumber()}</strong> supprimée avec succès"
        ]);
    }

    /**
     * @Route("/voir/template/{clientOrder}", name="client_order_show_template", options={"expose": true})
     * TODO HasPermission(Role::MANAGE_USERS) ??
     */
    public function editTemplate(EntityManagerInterface $manager, ClientOrder $clientOrder): Response {
        $roles = $manager->getRepository(Role::class)->findBy(["active" => true]);

        return $this->json([
            "template" => $this->renderView("operation/client_order/modal/show.html.twig", [
                "clientOrder" => $clientOrder,
                "roles" => $roles,
            ])
        ]);
    }
}
