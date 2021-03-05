<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\Order;
use App\Entity\Role;
use App\Entity\TrackingMovement;
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

        return $this->render("tracking/order/index.html.twig", [
            "new_order" => new Order(),
            "orders" => $orders,
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

        $boxes = explode(",", $content->box);
        $depositTickets = explode(",", $content->depositTicket);

        if ($form->isValid()) {
            $order = new Order();
            $order->setDate(new DateTime());

            foreach ($boxes as $box) {
                if ($box) {
                    $box = $boxRepository->find($box);
                    if ($box) {
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
                }
            }

            $boxPrices = Stream::from($order->getBoxes()->toArray())
                ->reduce(function(float $carry, Box $box) {
                    return $carry + ($box->getType() ? $box->getType()->getPrice() : 0);
                }, 0);

            foreach ($depositTickets as $depositTicket) {
                if ($depositTicket) {
                    $depositTicket = $depositTicketRepository->find($depositTicket);
                    if ($depositTicket) {
                        $order->addDepositTicket($depositTicket);
                        $depositTicket->setState(DepositTicket::SPENT);
                    }
                }
            }

            $depositTicketPrices = Stream::from($order->getDepositTickets()->toArray())
                ->reduce(function(float $carry, DepositTicket $depositTicket) {
                    $box = $depositTicket->getBox();
                    return $carry + ($box->getType() ? $box->getType()->getPrice() : 0);
                }, 0);

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
        $content = (object)$request->request->all();
        $order = $manager->getRepository(Order::class)->find($content->id);

        if ($order) {
            foreach ($order->getBoxes() as $box) {
                $previousMovement = $manager->getRepository(TrackingMovement::class)->findPreviousMovement($box);

                $movement = (new TrackingMovement())
                    ->setBox($box)
                    ->setDate(new DateTime())
                    ->setState(Box::AVAILABLE)
                    ->setLocation($previousMovement->getLocation())
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
                "msg" => "Commande supprimée avec succès"
            ]);
        } else {

            return $this->json(["success" => false,
                "msg" => "Cette commande n'existe pas"]);
        }
    }

}
