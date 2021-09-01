<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\CounterOrder;
use App\Entity\Role;
use App\Entity\BoxRecord;
use App\Helper\FormatHelper;
use App\Repository\CounterOrderRepository;
use App\Service\BoxRecordService;
use App\Service\BoxStateService;
use App\Service\CounterOrderService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/operation/commande-comptoir")
 */
class CounterOrderController extends AbstractController {

    /** @Required */
    public CounterOrderService $service;

    /**
     * @Route("/liste", name="counter_orders_list")
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("operation/counter_order/index.html.twig", [
            "new_order" => new CounterOrder(),
            "initial_orders" => $this->api($request, $manager)->getContent(),
            "orders_order" => CounterOrderRepository::DEFAULT_DATATABLE_ORDER,
        ]);
    }

    /**
     * @Route("/api", name="counter_orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(CounterOrder::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $data = [];
        /** @var CounterOrder $order */
        foreach ($orders["data"] as $order) {
            $data[] = [
                "id" => $order->getId(),
                "boxes" => FormatHelper::boxes($order->getBoxes()),
                "depositTickets" => FormatHelper::depositTickets($order->getDepositTickets()),
                "location" => FormatHelper::named($order->getLocation()),
                "boxPrice" => FormatHelper::price($order->getBoxPrice()),
                "depositTicketPrice" => FormatHelper::price($order->getDepositTicketPrice()),
                "totalCost" => FormatHelper::price($order->getBoxPrice() - $order->getDepositTicketPrice()),
                "user" => FormatHelper::user($order->getUser()),
                "client" => FormatHelper::named($order->getClient()),
                "date" => FormatHelper::datetime($order->getDate()),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "deletable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $orders["total"],
            "recordsFiltered" => $orders["filtered"],
        ]);
    }

    /**
     * @Route("/info/{type}/{number}", name="counter_order_info", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function info(EntityManagerInterface $manager, string $type, string $number): Response {
        if ($type === "box") {
            $box = $manager->getRepository(Box::class)
                ->findOneBy(["number" => $number]);

            if (!$box) {
                return $this->json([
                    "success" => false,
                    "unique" => true,
                    "message" => "La Box $number n'existe pas",
                ]);
            }

            if ($box->getState() !== BoxStateService::STATE_BOX_CLIENT) {
                return $this->json([
                    "success" => false,
                    "unique" => true,
                    "message" => "La Box $number n'est pas en statut client et ne peut pas être remise à un consommateur",
                ]);
            }

            return $this->json([
                "success" => true,
                "number" => $box->getNumber(),
                "price" => floatval($box->getType()->getPrice()),
            ]);
        } else if ($type === "ticket") {
            $ticket = $manager->getRepository(DepositTicket::class)
                ->findOneBy(["number" => $number]);

            if (!$ticket) {
                return $this->json([
                    "success" => false,
                    "unique" => true,
                    "message" => "Le ticket‑consigne $number n'existe pas",
                ]);
            }

            $now = new DateTime();
            if ($ticket->getValidityDate() < $now && $ticket->getState() === DepositTicket::VALID) {
                $ticket->setState(DepositTicket::EXPIRED);
                $manager->flush();
            }

            if ($ticket->getState() !== DepositTicket::VALID) {
                return $this->json([
                    "success" => false,
                    "unique" => true,
                    "message" => "Le ticket‑consigne $number a expiré ou a déjà été utilisé",
                ]);
            }

            return $this->json([
                "success" => true,
                "number" => $ticket->getNumber(),
                "price" => floatval($ticket->getBox()->getType()->getPrice()),
            ]);
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * @Route("/template/box", name="counter_order_boxes_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function boxesTemplate(): Response {
        return $this->json($this->service->renderBoxes());
    }

    /**
     * @Route("/submit/box", name="counter_order_boxes_submit", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function boxes(): Response {
        $this->service->update(Box::class);

        return $this->json([
            "success" => true,
            "modal" => $this->service->renderDepositTickets(),
        ]);
    }

    /**
     * @Route("/template/deposit-ticket", name="counter_order_deposit_tickets_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function depositTicketsTemplate(): Response {
        return $this->json($this->service->renderDepositTickets());
    }

    /**
     * @Route("/submit/deposit-ticket", name="counter_order_deposit_tickets_submit", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function depositTickets(Request $request): Response {
        $this->service->update(DepositTicket::class);

        if ($request->request->get("previous", 0)) {
            $modal = $this->service->renderBoxes();
        } else {
            $modal = $this->service->renderPayment();
        }
        return $this->json([
            "success" => true,
            "modal" => $modal,
        ]);
    }

    /**
     * @Route("/submit/confirmation", name="counter_order_confirm", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function confirm(Request $request, EntityManagerInterface $manager, BoxRecordService $boxRecordService): Response {
        if ($request->request->get("previous", 0)) {
            return $this->json([
                "success" => true,
                "modal" => $this->service->renderDepositTickets(),
            ]);
        }

        $boxes = $this->service->get(Box::class);
        $tickets = $this->service->get(DepositTicket::class);

        if (empty($boxes) && empty($tickets)) {
            return $this->json([
                "success" => true,
                "message" => "La commande comptoir ne peut pas être vide",
            ]);
        }

        $client = isset($boxes[0]) ? $boxes[0]->getOwner() : null;

        $order = (new CounterOrder())
            ->setLocation(isset($boxes[0]) ? $boxes[0]->getLocation() : null)
            ->setUser($this->getUser())
            ->setClient($client)
            ->setDate(new DateTime())
            ->setBoxes($boxes)
            ->setDepositTickets($tickets)
            ->setBoxPrice($this->service->getBoxesPrice())
            ->setDepositTicketPrice($this->service->getTicketsPrice());

        foreach ($boxes as $box) {
            $previous = clone $box;
            $box->setLocation($client->getOutLocation())
                ->setState(BoxStateService::STATE_BOX_CONSUMER);

            $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
        }

        foreach ($tickets as $ticket) {
            $ticket->setState(DepositTicket::SPENT)
                ->setUseDate(new DateTime());
        }

        $manager->persist($order);
        $manager->flush();

        $this->service->clear(true);

        return $this->json([
            "success" => true,
            "modal" => $this->service->renderConfirmation(),
            "reload" => true,
        ]);
    }

    /**
     * @Route("/supprimer", name="counter_order_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_COUNTER_ORDERS, Role::REDIRECT_NEW_COUNTER_ORDER)
     */
    public function delete(Request $request,
                           BoxRecordService $boxRecordService,
                           EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $order = $manager->getRepository(CounterOrder::class)->find($content->id);

        if ($order) {
            foreach ($order->getBoxes() as $box) {
                $previousMovement = $manager->getRepository(BoxRecord::class)->findPreviousTrackingMovement($box);

                $previous = clone $box;
                $box->setState(BoxStateService::STATE_BOX_CLIENT)
                    ->setLocation($previousMovement ? $previousMovement->getLocation() : null);

                $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
            }

            foreach ($order->getDepositTickets() as $depositTicket) {
                $depositTicket->setState(DepositTicket::SPENT);
            }

            $manager->remove($order);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Commande comptoir supprimée avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Cette commande comptoir n'existe pas"
            ]);
        }
    }

}
