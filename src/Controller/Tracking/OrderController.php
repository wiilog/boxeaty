<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\Order;
use App\Entity\Role;
use App\Entity\TrackingMovement;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/passage-caisse")
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
        foreach ($orders["data"] as $order) {
            $data[] = [
                "id" => $order->getId(),
                "boxes" => FormatHelper::boxes($order->getBoxes()),
                "depositTickets" => FormatHelper::depositTickets($order->getDepositTickets()),
                "location" => $order->getLocation() ? $order->getLocation()->getName() : "",
                "totalBoxAmount" => $order->getTotalBoxAmount(),
                "totalDepositTicketAmount" => $order->getTotalDepositTicketAmount(),
                "totalCost" => $order->getTotalCost(),
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
    public function new(Request $request, EntityManagerInterface $manager): Response {
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

            foreach ($boxes as $box) {
                $order->addBox($box);
                $movement = (new TrackingMovement())
                    ->setBox($box)
                    ->setDate(new DateTime())
                    ->setState(Box::CONSUMER)
                    ->setLocation(null)
                    ->setQuality($box->getQuality())
                    ->setClient($box->getOwner())
                    ->setUser($this->getUser());

                $manager->persist($movement);
                $box->fromTrackingMovement($movement);
            }

            $boxPrices = Stream::from($order->getBoxes())
                ->reduce(function(float $carry, Box $box) {
                    return $carry + ($box->getType() ? $box->getType()->getPrice() : 0);
                }, 0);

            foreach ($depositTickets as $depositTicket) {
                $order->addDepositTicket($depositTicket);
                $depositTicket
                    ->setState(DepositTicket::SPENT)
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

            $order->setUser($this->getUser());
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
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $order = $manager->getRepository(Order::class)->find($content->id);

        if ($order) {
            foreach ($order->getBoxes() as $box) {
                $previousMovement = $manager->getRepository(TrackingMovement::class)->findPreviousMovement($box);

                $movement = (new TrackingMovement())
                    ->setBox($box)
                    ->setDate(new DateTime())
                    ->setState(Box::AVAILABLE)
                    ->setLocation($previousMovement ? $previousMovement->getLocation() : null)
                    ->setQuality($box->getQuality())
                    ->setClient($box->getOwner())
                    ->setUser($this->getUser());

                $manager->persist($movement);
                $box->fromTrackingMovement($movement);
            }

            foreach ($order->getDepositTickets() as $depositTicket) {
                $depositTicket->setState(DepositTicket::SPENT);
            }

            $manager->remove($order);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Passage en caisse supprimé avec succès"
            ]);
        } else {

            return $this->json([
                "success" => false,
                "msg" => "Ce passage en caisse n'existe pas"
            ]);
        }
    }

}
