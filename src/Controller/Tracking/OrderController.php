<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\Order;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/commandes")
 */
class OrderController extends AbstractController {

    /**
     * @Route("/liste", name="orders_list")
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function list(EntityManagerInterface $manager): Response {
        $orders = $manager->getRepository(Order::class)->findBy([], ['id' => 'DESC']);
        $boxes = $manager->getRepository(Box::class)->findAll();
        $depositTickets = $manager->getRepository(DepositTicket::class)->findAll();

        return $this->render("tracking/order/index.html.twig", [
            "new_order" => new Order(),
            "orders" => $orders,
            "boxes" => $boxes,
            "depositTickets" => $depositTickets,
        ]);
    }

    /**
     * @Route("/api", name="orders_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $depositTickets = $manager->getRepository(DepositTicket::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/nouveau", name="order_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_ORDERS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object) $request->request->all();
        $boxRepository = $manager->getRepository(Box::class);
        $depositTicketRepository = $manager->getRepository(DepositTicket::class);

        $boxes = explode(",", $content->box);
        $depositTickets = explode(",", $content->depositTicket);

        if($form->isValid()) {
            $order = new Order();
            $order
                ->setDate(new DateTime());

            foreach ($boxes as $box) {
                if($box) {
                    $box = $boxRepository->findOneBy(['number' => $box]);
                    if($box) {
                        $order->addBox($box);
                    }
                }
            }

            $boxPrices = array_sum(Stream::from($order->getBoxes()->toArray())
                ->map(function (Box $box) {
                    return $box->getType() ? $box->getType()->getPrice() : 0;
                })
                ->toArray());

            foreach ($depositTickets as $depositTicket) {
                if($depositTicket) {
                    $depositTicket = $depositTicketRepository->findOneBy(['number' => $depositTicket]);
                    if($depositTicket) {
                        $order->addDepositTicket($depositTicket);
                    }
                }
            }

            $depositTicketPrices = array_sum(Stream::from($order->getDepositTickets()->toArray())
                ->map(function (DepositTicket $depositTicket) {
                    $box = $depositTicket->getBox();
                    return $box->getType() ? $box->getType()->getPrice() : 0;
                })
                ->toArray());

            $totalPrice = $boxPrices - $depositTicketPrices;
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
        $content = (object) $request->request->all();
        $order = $manager->getRepository(Order::class)->find($content->id);

        if ($order) {
            $manager->remove($order);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Commande supprimée avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Cette commande n'existe pas"
            ]);
        }
    }

}
