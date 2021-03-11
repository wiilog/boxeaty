<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\Order;
use App\Entity\Role;
use App\Entity\BoxRecord;
use App\Entity\TrackingMovement;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Service\BoxRecordService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/scan-box")
 */
class OrderController extends AbstractController {

    /**
     * @Route("/liste", name="orders_list")
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function list(EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(Order::class)->findBy([], ['id' => 'DESC']);

        return $this->render("tracking/order/index.html.twig", [
            "new_order" => new Order(),
            "orders" => $orders,
        ]);
    }

    /**
     * @Route("/api", name="orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(Order::class)
            ->findForDatatable(json_decode($request->getContent(), true), $this->getUser());

        $data = [];
        /** @var Order $order */
        foreach ($orders["data"] as $order) {
            $data[] = [
                "id" => $order->getId(),
                "boxes" => FormatHelper::boxes($order->getBoxes()),
                "depositTickets" => FormatHelper::depositTickets($order->getDepositTickets()),
                "location" => $order->getLocation() ? $order->getLocation()->getName() : "",
                "totalBoxAmount" => FormatHelper::price($order->getTotalBoxAmount()),
                "totalDepositTicketAmount" => FormatHelper::price($order->getTotalDepositTicketAmount()),
                "totalCost" => FormatHelper::price($order->getTotalCost()),
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
     * @Route("/nouveau", name="order_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function new(Request $request,
                        BoxRecordService $boxRecordService,
                        EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $boxRepository = $manager->getRepository(Box::class);
        $depositTicketRepository = $manager->getRepository(DepositTicket::class);

        $boxes = $boxRepository->findBy(["id" => explode(",", $content->box)]);
        $depositTickets = $depositTicketRepository->findBy(["id" => explode(",", $content->depositTicket)]);

        if (empty($boxes) && empty($depositTickets)) {
            $form->addError('box', 'Au moins une Box ou un ticket-consigne sont requis');
            $form->addError('depositTicket', 'Au moins une Box ou un ticket-consigne sont requis');
        }

        if ($form->isValid()) {
            $order = new Order();
            $order->setDate(new DateTime());

            /** @var User $orderUser */
            $orderUser = $this->getUser();

            foreach ($boxes as $box) {
                $order->addBox($box);

                $oldLocation = $box->getLocation();
                $oldState = $box->getState();
                $oldComment = $box->getComment();

                $box->setState(Box::CONSUMER)
                    ->setLocation(null);

                [$tracking, $record] = $boxRecordService->generateBoxRecords(
                    $box,
                    [
                        'location' => $oldLocation,
                        'state' => $oldState,
                        'comment' => $oldComment
                    ],
                    $this->getUser()
                );

                if ($tracking) {
                    $manager->persist($tracking);
                }

                if ($record) {
                    $manager->persist($record);
                }
            }

            $boxPrices = Stream::from($order->getBoxes())
                ->reduce(function(float $carry, Box $box) {
                    return $carry + ($box->getType() ? $box->getType()->getPrice() : 0);
                }, 0);

            foreach ($depositTickets as $depositTicket) {
                $order->addDepositTicket($depositTicket);
                $depositTicket
                    ->setState(DepositTicket::SPENT)
                    ->setOrderUser($orderUser)
                    ->setUseDate(new DateTime());
            }

            $depositTicketPrices = Stream::from($order->getDepositTickets())
                ->reduce(function(float $carry, DepositTicket $depositTicket) {
                    $box = $depositTicket->getBox();
                    return $carry + ($box->getType() ? $box->getType()->getPrice() : 0);
                }, 0);

            $totalPrice = $boxPrices - $depositTicketPrices;

            if(count($boxes) >= 1) {
                $order->setClient($boxes[0]->getOwner());
                $order->setLocation($boxes[0]->getLocation());
            } else if(!$this->getUser()->getClients()->isEmpty()) {
                $order->setClient($this->getUser()->getClients()[0]);
            }

            $order->setUser($orderUser);
            $order->setTotalBoxAmount($boxPrices);
            $order->setTotalDepositTicketAmount($depositTicketPrices);
            $order->setTotalCost($totalPrice);

            $manager->persist($order);
            $manager->flush();

            return $this->json([
                "success" => true,
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="order_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function delete(Request $request,
                           BoxRecordService $boxRecordService,
                           EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $order = $manager->getRepository(Order::class)->find($content->id);

        if ($order) {
            foreach ($order->getBoxes() as $box) {
                $oldLocation = $box->getLocation();
                $oldState = $box->getState();
                $oldComment = $box->getComment();

                $previousMovement = $manager->getRepository(BoxRecord::class)->findPreviousTrackingMovement($box);

                $box->setState(Box::AVAILABLE)
                    ->setLocation($previousMovement ? $previousMovement->getLocation() : null);

                [$tracking, $record] = $boxRecordService->generateBoxRecords(
                    $box,
                    [
                        'location' => $oldLocation,
                        'state' => $oldState,
                        'comment' => $oldComment
                    ],
                    $this->getUser()
                );

                if ($tracking) {
                    $manager->persist($tracking);
                }

                if ($record) {
                    $manager->persist($record);
                }
            }

            foreach ($order->getDepositTickets() as $depositTicket) {
                $depositTicket->setState(DepositTicket::SPENT);
            }

            $manager->remove($order);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Scan Box supprimé avec succès"
            ]);
        } else {

            return $this->json([
                "success" => false,
                "msg" => "Ce scan Box n'existe pas"
            ]);
        }
    }

}
