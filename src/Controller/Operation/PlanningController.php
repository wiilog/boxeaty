<?php


namespace App\Controller\Operation;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\ClientOrder;
use App\Entity\Delivery;
use App\Entity\DeliveryMethod;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\GlobalSetting;
use App\Entity\Preparation;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Service\DeliveryRoundService;
use App\Service\Mailer;
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
    public function deliveryRound(Request                $request,
                                  DeliveryRoundService   $deliveryRoundService,
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
                    ->setStatus($deliveryStatus)
                    ->setTokens($order->getClient()->getClientOrderInformation()->getTokenAmount() ?? 0)
                    ->setDistance(0.0);

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
     * @Route("/lancement-livraison/template", name="planning_delivery_initialize_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function initializeDeliveryTemplate(Request $request, EntityManagerInterface $manager): Response {
        if ($request->query->get("depository")) {
            $depository = $manager->find(Depository::class, $request->query->get("depository"));
        }

        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_launch"),
            "template" => $this->renderView("operation/planning/modal/start_delivery.html.twig", [
                "from" => $request->query->get("from"),
                "to" => $request->query->get("to"),
                "depository" => $depository ?? null,
            ])
        ]);
    }

    /**
     * @Route("/lancement-livraison/filtre", name="planning_delivery_launching_filter", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function depositoryFilter(EntityManagerInterface $manager, Request $request) {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $depository = $request->query->get("depository") ? $depositoryRepository->find($request->query->get("depository")) : null;
        $from = DateTime::createFromFormat("Y-m-d", $request->query->get("from"));
        $to = DateTime::createFromFormat("Y-m-d", $request->query->get("to"));

        if (!$depository || !$from || !$to) {
            return $this->json([
                "success" => false,
            ]);
        } else {
            return $this->json([
                "success" => true,
                "template" => $this->renderView('operation/planning/modal/deliveries_container.html.twig', [
                    "orders" => $clientOrderRepository->findLaunchableOrders($depository, $from, $to),
                ])
            ]);
        }
    }

    /**
     * @Route("/launch-delivery", name="planning_delivery_launch", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function launchDelivery(Request $request, EntityManagerInterface $manager, Mailer $mailer): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $statusRepository = $manager->getRepository(Status::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $ordersToStart = $request->query->get('assignedForStart');

        $statusPreparation = $statusRepository->findOneBy(["code" => Status::CODE_PREPARATION_TO_PREPARE]);
        $statusOrder = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_TRANSIT]);
        $depository = $depositoryRepository->findOneBy(["id" => $request->query->get('depository')]);

        foreach ($ordersToStart as $order) {
            $order = $clientOrderRepository->find($order);
            $order->setStatus($statusOrder);

            $preparation = (new Preparation())
                ->setStatus($statusPreparation)
                ->setDepository($depository)
                ->setOrder($order);

            $manager->persist($preparation);

            if($order->getClient()->isMailNotificationOrderPreparation()) {
                $content = $this->renderView("emails/mail_delivery_order.html.twig", [
                    "order" => $order,
                ]);

                $mailer->send($order->getClient()->getContact(), "Commande en préparation", $content);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
        ]);
    }

    /**
     * @Route("/delivery_start_check_stock", name="planning_delivery_start_check_stock", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function checkStock(Request $request, EntityManagerInterface $manager) {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $boxTypeRepository = $manager->getRepository(BoxType::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $ordersToStart = $request->query->get('assignedForStart');
        $depository = $depositoryRepository->find($request->query->get('depository'));

        $globalSettingRepository = $manager->getRepository(GlobalSetting::class);
        $defaultCrateTypeId = $globalSettingRepository->getValue(GlobalSetting::DEFAULT_CRATE_TYPE);
        $defaultCrateType = !empty($defaultCrateTypeId)
            ? $boxTypeRepository->find($defaultCrateTypeId)
            : null;

        // add all the ordered boxes (and crates) to the array
        // with the total quantity
        $orderedBoxTypes = [];
        foreach ($ordersToStart as $orderToStart) {
            $order = $clientOrderRepository->find($orderToStart);
            $closed = $order->getClient()->getClientOrderInformation()->isClosedParkOrder();
            $owner = $closed ? $order->getClient()->getId() : Box::OWNER_BOXEATY;
dump($defaultCrateType->getId());
            if (!isset($orderedBoxTypes[$defaultCrateType->getId()][$owner])) {
                $closed = $order->getClient()->getClientOrderInformation()->isClosedParkOrder();

                $orderedBoxTypes[$defaultCrateType->getId()][$owner] = [
                    'quantity' => 0,
                    'orders' => [],
                    'name' => $defaultCrateType->getName(),
                    'client' => $closed ? $order->getClient()->getName() : "BoxEaty",
                    'clientId' => $order->getClient()->getId(),
                    'clientClosed' => $closed,
                ];
            }

            $orderedBoxTypes[$defaultCrateType->getId()][$owner]['quantity'] += $order->getCratesAmount();

            $lines = $order->getLines();
            foreach ($lines as $line) {
                $boxType = $line->getBoxType();
                if ($boxType->getName() === BoxType::STARTER_KIT) {
                    continue;
                }

                $boxTypeId = $boxType->getId();
                $quantity = $line->getQuantity();
                if (!isset($orderedBoxTypes[$boxTypeId][$owner])) {
                    $orderedBoxTypes[$boxTypeId][$owner] = [
                        'quantity' => 0,
                        'orders' => [],
                        'name' => $boxType->getName(),
                        'client' => $closed ? $order->getClient()->getName() : "BoxEaty",
                        'clientId' => $order->getClient()->getId(),
                        'clientClosed' => $closed,
                    ];
                }

                $orderedBoxTypes[$boxTypeId][$owner]['quantity'] += $quantity;

                if (!in_array($order->getId(), $orderedBoxTypes[$boxTypeId][$owner]['orders'])) {
                    $orderedBoxTypes[$boxTypeId][$owner]['orders'][] = $order->getId();
                }
            }
        }

        $availableInDepository = $boxTypeRepository->countAvailableInDepository($depository, $defaultCrateTypeId, array_keys($orderedBoxTypes));

        $unavailableOrders = [];
        $availableBoxTypes = [];
        foreach ($orderedBoxTypes as $boxTypeId => $clients) {
            foreach ($clients as $client => $ordered) {
                $orderedQuantity = $ordered['quantity'];
                $orders = $ordered['orders'];
                $name = $ordered['name'];

                $availableQuantity = $availableInDepository[$boxTypeId][$client] ?? 0;

                if ($orderedQuantity > $availableQuantity) {
                    foreach ($orders as $order) {
                        if (!in_array($order, $unavailableOrders)) {
                            $unavailableOrders[] = $order;
                        }
                    }
                }

                $availableBoxTypes[] = [
                    "name" => $name,
                    "orderedQuantity" => $orderedQuantity,
                    "availableQuantity" => $availableQuantity,
                    "client" => $ordered["client"],
                ];
            }
        }

        return $this->json([
            "success" => true,
            "availableBoxTypeData" => $availableBoxTypes,
            "unavailableOrders" => $unavailableOrders,
        ]);
    }

    /**
     * @Route("/delivery_validate_template", name="planning_delivery_validate_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function validateDeliveryTemplate(Request $request, EntityManagerInterface $manager) {
        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_validate", [
                "order" => $request->query->get('order')
            ]),
            "template" => $this->renderView("operation/planning/modal/validate_delivery.html.twig")
        ]);
    }

    /**
     * @Route("/delivery_validate", name="planning_delivery_validate", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function validateDelivery(Request $request, EntityManagerInterface $manager) {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $statusRepository = $manager->getRepository(Status::class);

        $order = $clientOrderRepository->find($request->query->get('order'));
        $status = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_PLANNED]);

        $order->setStatus($status)
            ->setValidator($this->getUser())
            ->setValidatedAt(new DateTime());

        $manager->persist($order);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
        ]);
    }

}
