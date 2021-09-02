<?php


namespace App\Controller\Operation;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\BoxType;
use App\Entity\Client;
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
use App\Service\ClientOrderService;
use App\Service\DeliveryRoundService;
use App\Service\Mailer;
use App\Service\UniqueNumberService;
use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
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

        $from->setTime(0, 0);
        $to->setTime(23, 59);

        if ($from->diff($to, true)->days > 21) {
            return $this->json([
                "success" => false,
                "message" => "La planification ne peut afficher que 20 jours maximum"
            ]);
        }

        //group orders by date
        $ordersByDate = [];
        foreach ($clientOrderRepository->findBetween($this->getUser(), $from, $to, $request->query->all()) as $order) {
            $ordersByDate[FormatHelper::date($order->getExpectedDelivery(), "Ymd")][] = $order;
        }

        $sort = array_flip(Status::ORDER_STATUS_HIERARCHY);

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
     * @Route("/{order}/mail-commande-retard", name="planning_send_late_order_mail", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function lateOrderEmail(ClientOrder $order): Response {
        //TODO: envoyer le mail, en attente des retours de benoit??

        return $this->json([
            "success" => false,
            "message" => "Aucun mail envoyé : non développé",
        ]);
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
                "orders" => $clientOrderRepository->findDeliveriesBetween($this->getUser(), $from, $to, $request->query->all()),
            ])
        ]);
    }

    /**
     * @Route("/tournee", name="planning_delivery_round", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function deliveryRound(Request                $request,
                                  DeliveryRoundService   $deliveryRoundService,
                                  UniqueNumberService    $uniqueNumberService,
                                  EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $deliverer = isset($content->deliverer) ? $manager->getRepository(User::class)->find($content->deliverer) : null;
        $method = isset($content->method) ? $manager->getRepository(DeliveryMethod::class)->find($content->method) : null;
        $depository = isset($content->depository) ? $manager->getRepository(Depository::class)->find($content->depository) : null;
        $orders = $manager->getRepository(ClientOrder::class)->findBy(["id" => explode(",", $content->assignedForRound)]);

        if (count($orders) === 0) {
            $form->addError("Vous devez sélectionner au moins une livraison");
        }

        if ($form->isValid()) {
            $statusRepository = $manager->getRepository(Status::class);

            $orderAwaitingDeliverer = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_AWAITING_DELIVERER]);
            $deliveryAwaitingDeliverer = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_AWAITING_DELIVERER]);
            $deliveryPreparing = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_PREPARING]);

            foreach ($orders as $order) {
                $delivery = (new Delivery())
                    ->setOrder($order)
                    ->setTokens($order->getClient()->getClientOrderInformation()->getTokenAmount() ?? 0)
                    ->setDistance(0.0);

                if ($order->hasStatusCode(Status::CODE_ORDER_PREPARED)) {
                    $order->setStatus($orderAwaitingDeliverer);
                    $delivery->setStatus($deliveryAwaitingDeliverer);
                } else {
                    $delivery->setStatus($deliveryPreparing);
                }

                $manager->persist($delivery);
            }

            $round = (new DeliveryRound())
                ->setNumber($uniqueNumberService->createUniqueNumber(DeliveryRound::class))
                ->setDeliverer($deliverer)
                ->setDeliveryMethod($method)
                ->setDepository($depository)
                ->setOrders($orders)
                ->setOrder(Stream::from($orders)
                    ->keymap(fn(ClientOrder $order, int $i) => [$order->getId(), $i])
                    ->toArray())
                ->setCost($content->cost)
                ->setDistance($content->distance)
                ->setCreated(new DateTime());

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

        $now = date('Y-m-d');
        return $this->json([
            "submit" => $this->generateUrl("planning_delivery_launch"),
            "template" => $this->renderView("operation/planning/modal/start_delivery.html.twig", [
                "now" => date('Y-m-d', strtotime($now . '+ 1 days')),
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
                    "orders" => $clientOrderRepository->findLaunchableOrders($this->getUser(), $depository, $from, $to),
                ])
            ]);
        }
    }

    /**
     * @Route("/launch-delivery", name="planning_delivery_launch", options={"expose": true})
     * @HasPermission(Role::MANAGE_PLANNING)
     */
    public function launchDelivery(Request                $request,
                                   Mailer                 $mailer,
                                   ClientOrderService     $clientOrderService,
                                   EntityManagerInterface $manager): Response {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $statusRepository = $manager->getRepository(Status::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $ordersToStart = $request->query->get('assignedForStart');

        $statusPreparation = $statusRepository->findOneBy(["code" => Status::CODE_PREPARATION_TO_PREPARE]);
        $statusOrder = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_PREPARING]);
        $depository = $depositoryRepository->findOneBy(["id" => $request->query->get('depository')]);

        /** @var User $user */
        $user = $this->getUser();

        foreach ($ordersToStart as $order) {
            $order = $clientOrderRepository->find($order);

            $clientOrderService->updateClientOrderStatus($order, $statusOrder, $user);

            $preparation = (new Preparation())
                ->setStatus($statusPreparation)
                ->setDepository($depository)
                ->setOrder($order);

            $manager->persist($preparation);

            $deliverer = $order->getDeliveryRound()->getDeliverer();
            $content = $this->renderView("emails/delivery_round.html.twig", [
                "expectedDelivery" => $order->getExpectedDelivery(),
                "deliveryRound" => $order->getDeliveryRound()
            ]);

            $mailer->send($deliverer, "BoxEaty - Affectation de tournée", $content);
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
        $clientRepository = $manager->getRepository(Client::class);
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $boxTypeRepository = $manager->getRepository(BoxType::class);
        $depositoryRepository = $manager->getRepository(Depository::class);

        $ordersToStart = $request->query->get('assignedForStart');
        $depository = $depositoryRepository->find($request->query->get('depository'));

        $globalSettingRepository = $manager->getRepository(GlobalSetting::class);
        $defaultCrateTypeId = $globalSettingRepository->getValue(GlobalSetting::DEFAULT_CRATE_TYPE);

        if (!isset($defaultCrateTypeId)) {
            return $this->json([
                "success" => false,
                "message" => "Vous devez renseigner une caisse par défaut dans le paramétrage global",
            ]);
        }

        $boxeaty = $clientRepository->findOneBy(["name" => Client::BOXEATY]);
        $defaultCrateType = !empty($defaultCrateTypeId) ? $boxTypeRepository->find($defaultCrateTypeId) : null;

        // add all the ordered boxes (and crates) to the array
        // with the total quantity
        $orderedBoxTypes = [];
        foreach ($ordersToStart as $orderToStart) {
            $order = $clientOrderRepository->find($orderToStart);
            $closed = $order->getClient()->getClientOrderInformation()->isClosedParkOrder();
            $owner = $closed ? $order->getClient()->getId() : $boxeaty->getId();

            if (!isset($orderedBoxTypes[$defaultCrateType->getId()][$owner])) {
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
    public function validateDelivery(Request                $request,
                                     ClientOrderService     $clientOrderService,
                                     EntityManagerInterface $manager) {
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);
        $statusRepository = $manager->getRepository(Status::class);

        $order = $clientOrderRepository->find($request->query->get('order'));
        $status = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_PLANNED]);

        /** @var User $user */
        $user = $this->getUser();

        $clientOrderService->updateClientOrderStatus($order, $status, $user);

        $order
            ->setValidator($user)
            ->setValidatedAt(new DateTime());

        $manager->persist($order);
        $manager->flush();

        return $this->json([
            "success" => true,
            "message" => "Lancement effectuée avec succès",
        ]);
    }

}
