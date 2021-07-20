<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\ClientOrder;
use App\Entity\Role;
use App\Helper\FormatHelper;
use App\Repository\ClientOrderRepository;
use App\Service\CounterOrderService;
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
     * @Route("/liste", name="client_orders_list")
     * @HasPermission(Role::MANAGE_CLIENT_ORDERS, ROLE::VIEW_ALL_ORDERS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("operation/client_order/index.html.twig", [
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
                "status" => $order->getStatus(),
                "automatic" => $order->getAutomatic(),
                "boxes" => FormatHelper::boxes($order->getBoxes()),
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

        return $this->json([
            "success" => true,
            "message" => "Commande client <strong>{$clientOrder->getNumber()}</strong> supprimée avec succès"
        ]);
    }

}