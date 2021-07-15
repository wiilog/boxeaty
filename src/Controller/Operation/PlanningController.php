<?php


namespace App\Controller\Operation;

use App\Entity\ClientOrder;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\Role;
use App\Annotation\HasPermission;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/operations/planification")
 */
class PlanningController extends AbstractController {

    /**
     * @Route("/", name="planning")
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("operation/planning/index.html.twig", [
            "content" => $this->content($request, $manager, false)->getContent(),
        ]);
    }

    /**
     * @Route("/contenu", name="planning_content", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function content(Request $request, EntityManagerInterface $manager, bool $json = true): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        if ($request->query->has("from")) {
            $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        } else {
            $from = new DateTime();
        }

        if ($request->query->has("to")) {
            $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));
        } else {
            $to = (clone $from)->modify("+20 days");
        }

        //group orders by date
        $ordersByDate = [];
        foreach ($clientOrderRepository->findBetween($from, $to, $request->query->all()) as $order) {
            $ordersByDate[FormatHelper::date($order->getExpectedDelivery(), "Ymd")][] = $order;
        }

        $sort = [
            Status::ORDER_TO_VALIDATE => 1,
            Status::ORDER_PLANNED => 2,
            Status::ORDER_TRANSIT => 3,
        ];

        //generate cards configuration for twig
        $planning = [];
        $period = new DatePeriod($from, new DateInterval("P1D"), $to);
        foreach ($period as $date) {
            $orders = $ordersByDate[$date->format(FormatHelper::DATE_COMPACT)] ?? [];

            $column = [
                "title" => FormatHelper::weekDay($date) . " " . $date->format("d") . " " . FormatHelper::month($date),
                "date" => $date->format("Y-m-d"),
                "orders" => Stream::from($orders)
                    ->sort(fn($a, $b) => $sort[$a->getStatus()->getCode()] - $sort[$b->getStatus()->getCode()])
                    ->values(),
            ];

            $planning[] = $column;
        }

        if ($json) {
            return $this->json([
                "planning" => $this->renderView("operation/planning/content.html.twig", [
                    "planning" => $planning,
                ])
            ]);
        } else {
            return $this->render("operation/planning/content.html.twig", [
                "planning" => $planning,
            ]);
        }
    }

    /**
     * @Route("/changer-date/{order}", name="planning_change_date", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function changeDate(Request $request, EntityManagerInterface $manager, ClientOrder $order): Response {
        $date = DateTime::createFromFormat("Y-m-d", $request->getContent());

        $order->setExpectedDelivery($date);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "La date de livraison a été modifiée au <strong>{$date->format('d/m/Y')}</strong>",
            "card" => $this->renderView("operation/planning/card.html.twig", [
                "order" => $order,
            ]),
        ]);
    }

    /**
     * @Route("/tournee/template", name="planning_delivery_round_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function deliveryRoundTemplate(Request $request, EntityManagerInterface $manager): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));

        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_round"),
            "template" => $this->renderView("operation/planning/modal/new_delivery_round.html.twig", [
                "orders" => $clientOrderRepository->findDeliveriesBetween($from, $to, $request->query->all()),
            ])
        ]);
    }

    /**
     * @Route("/tournee", name="planning_delivery_round", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function deliveryRound(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $deliverer = isset($content->deliverer) ? $manager->getRepository(User::class)->find($content->deliverer) : null;
        $method = isset($content->method) ? $manager->getRepository(DeliveryMethod::class)->find($content->method) : null;
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : null;
        $orders = $manager->getRepository(ClientOrder::class)->findBy(["id" => $content->assigned]);

        if (count($orders) === 0) {
            $form->addError("Vous devez sélectionner au moins une livraison");
        }

        if ($form->isValid()) {
            $statusRepository = $manager->getRepository(Status::class);

            $prepared = Stream::from($orders)
                ->filter(fn(ClientOrder $order) => $order->getPreparation())
                ->filter(fn(ClientOrder $order) => $order->getPreparation()->getStatus()->getCode() === Status::PREPARATION_PREPARED)
                ->count();

            if ($prepared === count($orders)) {
                $status = $statusRepository->findOneBy(["code" => Status::ROUND_AWAITING_DELIVERER]);
                $deliveryStatus = $statusRepository->findOneBy(["code" => Status::DELIVERY_AWAITING_DELIVERER]);

                foreach ($orders as $order) {
                    $delivery = (new Delivery())
                        ->setOrder($order)
                        ->setStatus($deliveryStatus);

                    $manager->persist($delivery);
                }
            } else {
                $status = $statusRepository->findOneBy(["code" => Status::ROUND_CREATED]);
            }

            $round = (new DeliveryRound())
                ->setStatus($status)
                ->setDeliverer($deliverer)
                ->setDeliveryMethod($method)
                ->setDepository($depository)
                ->setOrders($orders)
                ->setOrder(Stream::from($orders)
                    ->keymap(fn(ClientOrder $order, int $i) => [$order->getId(), $i])
                    ->toArray())
                ->setCost($content->cost)
                ->setDistance($content->distance);

            $manager->persist($round);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Tournée créée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

}