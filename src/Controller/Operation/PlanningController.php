<?php


namespace App\Controller\Operation;

use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\Preparation;
use App\Entity\Role;
use App\Annotation\HasPermission;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Service\DeliveryRoundService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManager;
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
        $now = date('Y-m-d');
        return $this->render("operation/planning/index.html.twig", [
            "now" => date('Y-m-d', strtotime($now . '+ 1 days')),
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
            $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"))->modify("+1 day");
        } else {
            $to = (clone $from)->modify("+20 days");
        }

        //group orders by date
        $ordersByDate = [];
        foreach ($clientOrderRepository->findBetween($from, $to, $request->query->all()) as $order) {
            $ordersByDate[FormatHelper::date($order->getExpectedDelivery(), "Ymd")][] = $order;
        }

        $sort = [
            Status::CODE_ORDER_TO_VALIDATE_BOXEATY => 1,
            Status::CODE_ORDER_PLANNED => 2,
            Status::CODE_ORDER_TRANSIT => 3,
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
    public function deliveryRound(Request $request,
                                  DeliveryRoundService $deliveryRoundService,
                                  EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $deliverer = isset($content->deliverer) ? $manager->getRepository(User::class)->find($content->deliverer) : null;
        $method = isset($content->method) ? $manager->getRepository(DeliveryMethod::class)->find($content->method) : null;
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : null;
        $orders = $manager->getRepository(ClientOrder::class)->findBy(["id" => $content->assignedForRound]);

        if (count($orders) === 0) {
            $form->addError("Vous devez sélectionner au moins une livraison");
        }

        if ($form->isValid()) {
            $statusRepository = $manager->getRepository(Status::class);

            $preparedDeliveryStatus = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_PREPARED]);
            $preparingDeliveryStatus = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_PREPARING]);

            foreach ($orders as $order) {
                $preparation = $order->getPreparation();
                $deliveryStatus = ($preparation && $preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARED))
                    ? $preparedDeliveryStatus
                    : $preparingDeliveryStatus;

                $delivery = (new Delivery())
                    ->setOrder($order)
                    ->setStatus($deliveryStatus);

                $manager->persist($delivery);
            }

            $round = (new DeliveryRound())
                ->setDeliverer($deliverer)
                ->setDeliveryMethod($method)
                ->setDepository($depository)
                ->setOrders($orders)
                ->setOrder(Stream::from($orders)
                    ->keymap(fn(ClientOrder $order, int $i) => [$order->getId(), $i])
                    ->toArray())
                ->setCost($content->cost)
                ->setDistance($content->distance);

            $deliveryRoundService->updateDeliveryRound($manager, $round);

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

    /**
     * @Route("/initialize_delivery/template", name="planning_delivery_initialize_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function initializeDeliveryTemplate(EntityManagerInterface $manager, Request $request): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        dump($request->query->all());

        $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));

        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_initialize"),
            "template" => $this->renderView("operation/planning/modal/start_delivery.html.twig", [
                "from" => $from,
                "to" => $to,
                "orders" => $clientOrderRepository->findOrders($request->query->all(), $from, $to),
            ])
        ]);
    }

    /**
     * @Route("/initialize_delivery", name="planning_delivery_initialize", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function initializeDelivery(Request $request, EntityManagerInterface $manager): Response {
        $from = DateTime::createFromFormat("Y-m-d", $request->request->get("from"));
        $to = DateTime::createFromFormat("Y-m-d", $request->request->get("to"));

        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $statusRepository = $manager->getRepository(Status::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $ordersToStart = explode(",", $request->request->get('assignedForStart'));

        $statusDelivery = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_PLANNED]);
        $statusPreparation = $statusRepository->findOneBy(["code" => Status::CODE_PREPARATION_PREPARING]);
        $depository = $depositoryRepository->findOneBy(["id" => $request->request->get('depository')]);

        foreach($ordersToStart as $orderToStart){
            /** @var ClientOrder $order */
            $order = $clientOrderRepository->find($orderToStart);

            $preparation = (new Preparation())
                ->setStatus($statusPreparation)
                ->setDepository($depository)
                ->setOrder($order);
            $delivery = (new Delivery())
                ->setStatus($statusDelivery)
                ->setTokens(($order->getClient()->getClientOrderInformation() && $order->getClient()->getClientOrderInformation()->getTokenAmount()) ? $order->getClient()->getClientOrderInformation()->getTokenAmount() : 0)
                ->setOrder($order);

            $manager->persist($preparation);
            $manager->persist($delivery);
        }

        //$manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
            "assignedForStart" => $request->request->get('assignedForStart'),
            "from" => $from,
            "to" => $to,
            "depository" => $request->request->get('depository'),
        ]);
    }

    /**
     * @Route("/start_delivery/template", name="planning_delivery_start_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function startDeliveryTemplate(EntityManagerInterface $manager, Request $request){
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $depositoryRepository = $manager->getRepository(Depository::class);
        dump($request->query->all());
        $params = $request->query->get("params");
        $from = new DateTime($params["from"]);
        $to = new DateTime($params["to"]);

        $assignedForStart = explode(',', $request->query->get('assignedForStart'));
        $depository = $depositoryRepository->find($request->query->get('depository'));

        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_start"),
            "template" => $this->renderView("operation/planning/modal/calculation.html.twig", [
                "from" => $from,
                "to" => $to,
                "orders" => $clientOrderRepository->findOrders($request->query->all(), $from, $to),
                "assignedForStart" => $clientOrderRepository->findBy(["id" => $assignedForStart]),
                "depository" => $depository,
            ])
        ]);
    }

    /**
     * @Route("/start_delivery", name="planning_delivery_start", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function startDelivery(Request $request, EntityManagerInterface $manager): Response {
        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
            "assignedForStart" => $request->query->get('assignedForStart'),
        ]);
    }

    /**
     * @Route("/delivery_start_check_stock", name="planning_delivery_start_check_stock", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function checkStock(Request $request, EntityManagerInterface $manager){
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $ordersToStart = explode(",", $request->query->get('assignedForStart'));
        $depository = $request->query->get('depository');

        foreach ($ordersToStart as $orderToStart){
            /** @var ClientOrder $order */
            $order = $clientOrderRepository->find($orderToStart);

            $calculatedQuantity = 0 ;

            if($order->getLines() !== null){
                $lines = $order->getLines();
                foreach ($lines as $line){
                    /** @var ClientOrderLine $line */
                    $boxType = $line->getBoxType();
                    $calculatedQuantity = $calculatedQuantity + $line->getQuantity();
                }
            }
        }

        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
        ]);
    }

}
