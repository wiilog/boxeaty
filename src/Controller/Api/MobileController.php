<?php

namespace App\Controller\Api;

use App\Annotation\Authenticated;
use App\Controller\AbstractController;
use App\Entity\Attachment;
use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Collect;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\Location;
use App\Entity\Preparation;
use App\Entity\PreparationLine;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use App\Service\BoxRecordService;
use App\Service\BoxStateService;
use App\Service\ClientOrderService;
use App\Service\DeliveryRoundService;
use App\Service\Mailer;
use App\Service\PreparationService;
use App\Service\UniqueNumberService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;

/**
 * @Route("/api/mobile")
 */
class MobileController extends AbstractController {

    /**
     * @Route("/login", name="api_mobile_login")
     */
    public function login(Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $hasher): JsonResponse {
        $content = json_decode($request->getContent());

        $user = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($user && $hasher->isPasswordValid($user, $content->password)) {
            $user->setApiKey(bin2hex(random_bytes(16)));
            $manager->flush();

            return $this->json([
                "success" => true,
                "user" => [
                    "username" => $user->getUsername(),
                    "token" => $user->getApiKey(),
                    "rights" => [
                        "preparations" => $user->hasRight(Role::TREAT_PREPARATIONS),
                        "deliveries" => $user->hasRight(Role::TREAT_DELIVERIES),
                        "receptions" => $user->hasRight(Role::TREAT_RECEPTIONS),
                        "all_collects" => $user->hasRight(Role::TREAT_ALL_COLLECTS),
                    ],
                ],
            ]);
        }

        return $this->json([
            "success" => false,
            "message" => "Identifiants invalides"
        ]);
    }

    /**
     * @Route("/depositories", name="api_mobile_depositories")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function depositories(EntityManagerInterface $manager): JsonResponse {
        return $this->json($manager->getRepository(Depository::class)->getAll());
    }

    /**
     * @Route("/delivery-rounds", name="api_mobile_delivery_rounds")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function deliveryRounds(EntityManagerInterface $manager): JsonResponse {
        $now = new DateTime("today midnight");
        $rounds = $manager->getRepository(DeliveryRound::class)->findAwaitingDeliverer($this->getUser());

        $serialized = Stream::from($rounds)
            ->map(fn(DeliveryRound $round) => [
                "id" => $round->getId(),
                "number" => $round->getNumber(),
                "status" => $round->getStatus()->getCode(),
                "depository" => FormatHelper::named($round->getDepository()),
                "joined_clients" => Stream::from($round->getOrders())
                    ->map(fn(ClientOrder $order) => $order->getClient()->getName())
                    ->unique()
                    ->join(", "),
                "expected_date" => Stream::from($round->getOrders())
                    ->map(fn(ClientOrder $order) => $order->getExpectedDelivery())
                    ->sort()
                    ->first(),
                "crate_amount" => Stream::from($round->getOrders())
                    ->map(fn(ClientOrder $order) => $order->getCratesAmount())
                    ->sum(),
                "token_amount" => Stream::from($round->getOrders())
                    ->map(fn(ClientOrder $order) => $order->getClient()->getClientOrderInformation()->getTokenAmount())
                    ->sum(),
                "orders" => $round->getOrders()->map(fn(ClientOrder $order) => [
                    "id" => $order->getId(),
                    "delivered" => $order->hasStatusCode(Status::CODE_ORDER_FINISHED),
                    "crate_amount" => $order->getPreparation() ? $order->getPreparation()->getLines()->count() : -1,
                    "token_amount" => $order->getTokensAmount(),
                    "collect_required" => $order->isCollectRequired(),
                    "preparation" => $order->getPreparation() ? [
                        "id" => $order->getPreparation()->getId(),
                        "depository" => FormatHelper::named($order->getPreparation()->getDepository()),
                        "lines" => $order->getPreparation()->getLines()
                            ->map(fn(PreparationLine $line) => [
                                "crate" => $line->getCrate()->getNumber(),
                                "type" => FormatHelper::named($line->getCrate()->getType()),
                                "taken" => $line->isTaken(),
                                "deposited" => $line->isDeposited(),
                            ])
                            ->toArray(),
                    ] : null,
                    "client" => [
                        "id" => $order->getClient()->getId(),
                        "name" => FormatHelper::named($order->getClient()),
                        "address" => $order->getClient()->getAddress(),
                        "contact" => FormatHelper::user($order->getClient()->getContact()),
                        "phone" => $order->getClient()->getPhoneNumber(),
                        "latitude" => $order->getClient()->getLatitude(),
                        "longitude" => $order->getClient()->getLongitude(),
                    ],
                    "comment" => $order->getComment(),
                ]),
                "order" => $round->getOrder(),
            ])
            ->sort(fn(array $a, array $b) => $a["expected_date"] <=> $b["expected_date"])
            ->toArray();

        $result = [];
        foreach ($serialized as $round) {
            if ($round["expected_date"] < $now) {
                $result[$now->format("Y-m-d")][] = $round;
            } else {
                $result[$round["expected_date"]->format("Y-m-d")][] = $round;
            }
        }

        return $this->json($result);
    }

    /**
     * @Route("/deliveries/start", name="api_mobile_deliveries_start")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function deliveryStart(EntityManagerInterface $manager, Request $request, ClientOrderService $clientOrderService): JsonResponse {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);

        if ($order) {
            $statusRepository = $manager->getRepository(Status::class);

            $orderTransitStatus = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_TRANSIT]);
            $deliveryTransitStatus = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_TRANSIT]);

            foreach ($order->getDeliveryRound()->getOrders() as $o) {
                if($o->hasStatusCode(Status::CODE_ORDER_AWAITING_DELIVERER)) {
                    $clientOrderService->updateClientOrderStatus($o, $orderTransitStatus, $this->getUser());
                    $o->getDelivery()->setStatus($deliveryTransitStatus);
                }
            }

            $manager->flush();

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/deliveries/take", name="api_mobile_deliveries_take")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function deliveryTake(EntityManagerInterface $manager, Request $request, BoxRecordService $service): JsonResponse {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);
        $crate = $manager->getRepository(Box::class)->findOneBy(["number" => $data->crate]);

        if ($crate) {
            $line = $order->getPreparation()
                ->getLines()
                ->filter(fn(PreparationLine $line) => $line->getCrate()->getNumber() === $crate->getNumber())
                ->first();

            $line->setTaken(true);

            $offset = $crate->getLocation() ? $crate->getLocation()->getOffset() : null;

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                $previous = clone $box;
                $box->setLocation($offset);

                $service->generateBoxRecords($box, $previous, $this->getUser());
            }

            $manager->flush();

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/deliveries/deposit", name="api_mobile_deliveries_deposit")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function deliveryDeposit(EntityManagerInterface $manager, Request $request, BoxRecordService $service): JsonResponse {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);
        $crate = $manager->getRepository(Box::class)->findOneBy(["number" => $data->crate]);

        if ($crate) {
            $line = Stream::from($order->getPreparation()->getLines())
                ->filter(fn(PreparationLine $line) => $line->getCrate()->getNumber() === $crate->getNumber())
                ->first();

            $line->setDeposited(true);

            $location = Stream::from($order->getClient()->getLocations())
                ->filter(fn(Location $location) => $location->getType() === Location::RECEPTION)
                ->first();

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                $previous = clone $box;
                $box->setLocation($location)
                    ->setState(BoxStateService::STATE_BOX_CLIENT);

                $service->generateBoxRecords($box, $previous, $this->getUser());
            }

            $manager->flush();

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/deliveries/finish", name="api_mobile_deliveries_finish")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function finishDelivery(EntityManagerInterface $manager,
                                   Request                $request,
                                   AttachmentService      $attachmentService,
                                   ClientOrderService     $clientOrderService): JsonResponse {

        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);

        if ($order) {
            $deliveryRound = $order->getDeliveryRound();
            $delivery = $order->getDelivery();

            $orderStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_ORDER_FINISHED]);
            $deliveryStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_DELIVERY_DELIVERED]);
            $signature = $attachmentService->createAttachment(Attachment::TYPE_DELIVERY_SIGNATURE, ["signature", $data->signature]);
            $photo = $attachmentService->createAttachment(Attachment::TYPE_DELIVERY_PHOTO, ["photo", $data->photo]);

            $clientOrderService->updateClientOrderStatus($order, $orderStatus, $this->getUser());

            $order->setComment($data->comment);

            $delivery->setDistance($data->distance)
                ->setStatus($deliveryStatus)
                ->setSignature($signature)
                ->setPhoto($photo);

            $unfinishedDeliveries = $deliveryRound->getOrders()
                ->filter(fn(ClientOrder $order) => !$order->hasStatusCode(Status::CODE_ORDER_FINISHED))
                ->count();

            if ($unfinishedDeliveries === 0) {
                $status = $manager->getRepository(Status::class)->findOneBy(["code" => Status::CODE_ROUND_FINISHED]);
                $distance = Stream::from($deliveryRound->getOrders())
                    ->map(fn(ClientOrder $order) => $order->getDelivery()->getDistance())
                    ->sum();

                $deliveryRound->setStatus($status)
                    ->setDistance($distance);
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Livraison terminée",
                "round_finished" => $unfinishedDeliveries === 0,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/preparations", name="api_mobile_preparations")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function preparations(EntityManagerInterface $manager, Request $request): JsonResponse {
        $depositoryRepository = $manager->getRepository(Depository::class);
        $preparationRepository = $manager->getRepository(Preparation::class);

        $depositoryId = $request->query->get("depository");
        $depository = $depositoryId
            ? $depositoryRepository->find($depositoryId)
            : null;

        $preparations = $preparationRepository->getByDepository($depository, $this->getUser());

        $toPrepare = Stream::from($preparations)
            ->filter(fn($preparation) => !isset($preparation['operator']))
            ->values();
        $preparing = Stream::from($preparations)
            ->filter(fn($preparation) => isset($preparation['operator']))
            ->values();

        return $this->json([
            'toPrepare' => $toPrepare,
            'preparing' => $preparing
        ]);
    }

    /**
     * @Route("/locations", name="api_mobile_locations")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function locations(EntityManagerInterface $manager): JsonResponse {
        return $this->json($manager->getRepository(Location::class)->getAll());
    }

    /**
     * @Route("/qualities", name="api_mobile_qualities")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function qualities(EntityManagerInterface $manager): JsonResponse {
        return $this->json($manager->getRepository(Quality::class)->getAll());
    }

    /**
     * @Route("/crates", name="api_mobile_crates")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function crates(EntityManagerInterface $manager, Request $request): JsonResponse {
        $depository = $manager->getRepository(Depository::class)->find($request->query->get('depository'));
        return $this->json($manager->getRepository(Box::class)->getByDepository($depository));
    }

    /**
     * @Route("/box", name="api_mobile_box")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function box(EntityManagerInterface $manager, Request $request): JsonResponse {
        return $this->json($manager->getRepository(Box::class)->getByNumber($request->query->get('box')));
    }

    /**
     * @Route("/reverse-tracking", name="api_mobile_reverse_tracking")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function reverseTracking(EntityManagerInterface $manager, Request $request, BoxRecordService $boxRecordService): JsonResponse {
        $boxRepository = $manager->getRepository(Box::class);
        $locationRepository = $manager->getRepository(Location::class);
        $qualityRepository = $manager->getRepository(Quality::class);

        $content = json_decode($request->getContent());
        $content->boxes = explode(",", $content->boxes);

        /**
         * @var $boxes Box[]
         */
        $boxes = [];

        $boxes[] = $boxRepository->findOneBy(["number" => $content->crate]);
        foreach ($content->boxes as $box) {
            $boxes[] = $boxRepository->find($box);
        }

        $chosenQuality = $qualityRepository->find($content->quality);
        $chosenLocation = $locationRepository->find($content->location);
        foreach ($boxes as $box) {
            $previous = clone $box;
            $box->setLocation($chosenLocation)
                ->setQuality($chosenQuality);

            $boxRecordService->generateBoxRecords($box, $previous, $this->getUser(), function(BoxRecord $record) {
                $record->setState(BoxStateService::STATE_RECORD_IDENTIFIED);
            });
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/preparations/{preparation}", name="api_mobile_get_preparation", methods={"GET"})
     * @Authenticated(Authenticated::MOBILE)
     */
    public function getPreparation(EntityManagerInterface $entityManager,
                                   ClientOrderService     $clientOrderService,
                                   Preparation            $preparation): JsonResponse {
        if ((
                !$preparation->hasStatusCode(Status::CODE_PREPARATION_TO_PREPARE)
                && !$preparation->hasStatusCode(Status::CODE_PREPARATION_PREPARING)
            )
            || (
                $preparation->hasStatusCode(Status::CODE_PREPARATION_PREPARING)
                && $preparation->getOperator()
                && $this->getUser() !== $preparation->getOperator()
            )) {
            return $this->json([
                'success' => false,
                'message' => 'Préparation non trouvée'
            ]);
        }

        $clientOrder = $preparation->getOrder();
        $client = $clientOrder->getClient();

        $cart = $clientOrder->getLines()
            ->map(fn(ClientOrderLine $line) => [
                'boxType' => $line->getBoxType(),
                'quantity' => $line->getQuantity()
            ])
            ->toArray();

        $crates = $clientOrderService->getCartSplitting($entityManager, $client, $cart);

        return $this->json([
            'success' => true,
            'crates' => $crates
        ]);
    }

    /**
     * @Route("/preparations/{preparation}", name="api_mobile_patch_preparation", methods={"PATCH"})
     * @Authenticated(Authenticated::MOBILE)
     */
    public function patchPreparation(Request                $request,
                                     Preparation            $preparation,
                                     PreparationService     $preparationService,
                                     DeliveryRoundService   $deliveryRoundService,
                                     BoxRecordService       $boxRecordService,
                                     ClientOrderService     $clientOrderService,
                                     EntityManagerInterface $entityManager,
                                     Mailer                 $mailer): JsonResponse {

        $content = json_decode($request->getContent(), true);

        $preparing = $content['preparing'] ?? false;
        $statusRepository = $entityManager->getRepository(Status::class);

        if ($preparing) {
            if ($preparation->hasStatusCode(Status::CODE_PREPARATION_TO_PREPARE)) {
                $preparationStatus = $statusRepository->findOneBy(['code' => Status::CODE_PREPARATION_PREPARING]);
                $orderStatus = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_PREPARING]);

                $clientOrderService->updateClientOrderStatus($preparation->getOrder(), $orderStatus, $this->getUser());
                $preparation->setStatus($preparationStatus)
                    ->setOperator($this->getUser());

                $entityManager->flush();

                return $this->json([
                    "success" => true,
                ]);
            } else if (!$preparation->hasStatusCode(Status::CODE_PREPARATION_PREPARING)
                || $this->getUser() !== $preparation->getOperator()) {
                return $this->json([
                    'success' => false,
                    'message' => 'La préparation est en cours de préparation par un autre utilisateur'
                ]);
            } else {
                // $preparation->hasStatusCode(Status::CODE_PREPARATION_PREPARING)
                // AND $this->getUser() === $preparation->getOperator()
                return $this->json([
                    'success' => true
                ]);
            }

        } else if ($preparation->hasStatusCode(Status::CODE_PREPARATION_PREPARING)
            && $this->getUser() === $preparation->getOperator()) {
            $preparedStatus = $statusRepository->findOneBy(["code" => Status::CODE_PREPARATION_PREPARED]);
            $preparedDeliveryStatus = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_AWAITING_DELIVERER]);
            $preparedOrderStatus = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_PREPARED]);
            $awaitingDelivererStatus = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_AWAITING_DELIVERER]);

            $crates = $content['crates'] ?? [];

            $clientOrder = $preparation->getOrder();
            $result = $preparationService->handlePreparedCrates($entityManager, $clientOrder, $crates);

            $user = $this->getUser();

            if ($result['success']) {
                foreach ($result['entities'] as $crateData) {
                    $preparationLine = (new PreparationLine())
                        ->setPreparation($preparation)
                        ->setCrate($crateData['crate']);
                    $entityManager->persist($preparationLine);

                    foreach (Stream::from([$crateData['crate']], $crateData['boxes']) as $box) {
                        $previous = clone $box;
                        $box->setCrate($crateData['crate']->getId() !== $box->getId() ? $crateData['crate'] : null)
                            ->setLocation($box->getLocation()->getOffset())
                            ->setState(BoxStateService::STATE_BOX_UNAVAILABLE);

                        $boxRecordService->generateBoxRecords($box, $previous, $user);

                        $preparationLine->addBox($box);
                    }
                }

                $preparation->setStatus($preparedStatus);

                $delivery = $clientOrder->getDelivery();
                if ($delivery) {
                    $delivery->setStatus($preparedDeliveryStatus);

                    $clientOrderService->updateClientOrderStatus($clientOrder, $awaitingDelivererStatus, $this->getUser());
                } else {
                    $clientOrderService->updateClientOrderStatus($clientOrder, $preparedOrderStatus, $this->getUser());
                }

                $deliveryRound = $clientOrder->getDeliveryRound();
                if ($deliveryRound) {
                    $deliveryRoundService->updateDeliveryRound($entityManager, $deliveryRound);
                }

                $entityManager->flush();

                if ($clientOrder->getClient()->isMailNotificationOrderPreparation()) {
                    $content = $this->renderView("emails/mail_delivery_order.html.twig", [
                        "order" => $clientOrder,
                    ]);

                    $mailer->send($clientOrder->getClient()->getContact(), "Commande en préparation", $content);
                }

                return $this->json([
                    "success" => true,
                ]);
            } else {
                return $this->json([
                    "success" => false,
                    "message" => $result["message"]
                ]);
            }
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/available-crates", name="api_mobile_available_crates")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function availableCrates(EntityManagerInterface $manager, Request $request): JsonResponse {
        $clientRepository = $manager->getRepository(Client::class);
        $boxRepository = $manager->getRepository(Box::class);
        $preparationRepository = $manager->getRepository(Preparation::class);

        $preparation = $preparationRepository->find($request->query->get('preparation'));

        $clientFilter = $preparation
            ->getOrder()
            ->getClientClosedPark();

        if (!$clientFilter) {
            $clientFilter = $clientRepository->findOneBy(["name" => Client::BOXEATY]);
        }

        return $this->json($boxRepository->getPreparableCrates(
            $preparation,
            $clientFilter,
            $request->query->get("type")
        ));
    }

    /**
     * @Route("/available-boxes", name="api_mobile_get_boxes")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function availableBoxes(EntityManagerInterface $manager, Request $request): JsonResponse {
        $query = $request->query;
        $clientRepository = $manager->getRepository(Client::class);
        $preparationRepository = $manager->getRepository(Preparation::class);
        $boxRepository = $manager->getRepository(Box::class);

        $preparation = $preparationRepository->find($query->get('preparation'));

        $boxTypes = $preparation
            ? Stream::from($preparation->getOrder()->getLines())
                ->map(fn(ClientOrderLine $line) => [
                    $line->getBoxType()->getId()
                ])
                ->toArray()
            : [];

        $clientFilter = $preparation
            ->getOrder()
            ->getClientClosedPark();

        if (!$clientFilter) {
            $clientFilter = $clientRepository->findOneBy(["name" => Client::BOXEATY]);
        }

        return $this->json([
            "availableBoxes" => $boxRepository->getPreparableBoxes($preparation, $clientFilter, $boxTypes),
        ]);
    }

    /**
     * @Route("/box-informations", name="api_mobile_box_informations")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function boxInformations(EntityManagerInterface $manager, Request $request): JsonResponse {
        $box = $request->query->get('box');
        $isCrate = $request->query->get('isCrate');
        $crate = $request->query->get('crate');

        if($isCrate) {
            $box = $manager->getRepository(Box::class)->findOneBy(['number' => $box, 'isBox' => 0]);
        } else {
            $box = $manager->getRepository(Box::class)->findOneBy(['number' => $box]);
        }

        if ($crate) {
            $crate = $manager->getRepository(Box::class)->findOneBy(['number' => $crate, 'isBox' => 0]);
        }

        if ($box) {
            $type = $box->getType()->getName();
            $volume = $box->getType()->getVolume();
            $number = $box->getNumber();

            return $this->json([
                "success" => true,
                "data" => [
                    "number" => $number,
                    "type" => $type,
                    "volume" => $volume,
                    "crateVolume" => $crate ? $crate->getType()->getVolume() : 0
                ]
            ]);
        }

        return $this->json([
            "success" => false,
        ]);
    }

    /**
     * @Route("/moving", name="api_mobile_moving")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function moving(EntityManagerInterface $manager, Request $request, BoxRecordService $boxRecordService): JsonResponse {
        $boxRepository = $manager->getRepository(Box::class);
        $locationRepository = $manager->getRepository(Location::class);
        $qualityRepository = $manager->getRepository(Quality::class);

        $content = json_decode($request->getContent());
        $scannedBoxesAndCrates = Stream::from($content->scannedBoxesAndCrates)
            ->map(fn($box) => $box["number"])
            ->toArray();

        $chosenQuality = $qualityRepository->find($content->quality);
        $chosenLocation = $locationRepository->find($content->location);

        foreach ($scannedBoxesAndCrates as $scannedBoxOrCrate) {
            $box = $boxRepository->findOneBy(['number' => $scannedBoxOrCrate]);
            if ($box) {
                $previous = clone $box;
                $box->setLocation($chosenLocation)
                    ->setQuality($chosenQuality);

                $boxRecordService->generateBoxRecords($box, $previous, $this->getUser(), function (BoxRecord $record) {
                    $record->setState(BoxStateService::STATE_RECORD_IDENTIFIED);
                });
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/collects", name="api_mobile_get_collects", methods={"GET"})
     * @Authenticated(Authenticated::MOBILE)
     */
    public function collects(EntityManagerInterface $manager): JsonResponse {
        return $this->json($manager->getRepository(Collect::class)->getPendingCollects($this->getUser()));
    }

    /**
     * @Route("/collect-crates/{collect}", name="api_mobile_collect_crates")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function collectCrates(Collect $collect): JsonResponse {
        $collectCrates = Stream::from($collect->getCrates()->map(fn(Box $crate) => [
            "number" => $crate->getNumber(),
            "type" => $crate->getType()->getName()
        ]));

        return $this->json($collectCrates);
    }

    /**
     * @Route("/collects/{collect}", name="api_mobile_patch_collect", methods={"PATCH"})
     * @Authenticated(Authenticated::MOBILE)
     */
    public function patchCollect(Collect                $collect,
                                 Request                $request,
                                 EntityManagerInterface $manager,
                                 AttachmentService      $attachmentService,
                                 BoxRecordService       $boxRecordService): JsonResponse {
        $data = json_decode($request->getContent());

        $validate = $data->validate ?? false;

        if ($validate) {
            $dropLocation = $manager->find(Location::class, $data->drop_location);
            $crates = $collect->getCrates();

            foreach ($crates as $crate) {
                $previous = clone $crate;
                $crate
                    ->setState(BoxStateService::STATE_BOX_UNAVAILABLE)
                    ->setLocation($dropLocation);

                $boxRecordService->generateBoxRecords($crate, $previous, $this->getUser());
            }

            $collectStatus = $manager->getRepository(Status::class)->findOneBy(["code" => Status::CODE_COLLECT_FINISHED]);

            if ($data->data->photo) {
                $photo = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_PHOTO, ["photo", $data->data->photo]);
            }
            $signature = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_SIGNATURE, ["signature", $data->data->signature]);
            $comment = $data->data->comment;

            $collect
                ->setStatus($collectStatus)
                ->setDropSignature($signature)
                ->setDropPhoto($photo ?? null)
                ->setDropComment($comment ?? null)
                ->setTreatedAt(new DateTime())
                ->setTokens((int)$data->token_amount);

            $manager->flush();

            return $this->json([
                "success" => true
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/location", name="api_mobile_location")
     * @Authenticated(Authenticated::MOBILE)
     */
    public function location(Request $request, EntityManagerInterface $manager): JsonResponse {
        $location = $manager->getRepository(Location::class)->findOneBy([
            'name' => $request->query->get('location')
        ]);
        $client = $location->getClient();

        return $this->json([
            'name' => $location->getName(),
            'client' => $client ? $client->getName() : '-',
            'address' => $client ? $client->getAddress() : '-',
            'main_contact' => $client ? $client->getContact()->getUsername() : '-',
            'phone_number' => $client ? $client->getPhoneNumber() : '-',
        ]);
    }

    /**
     * @Route("/collects", name="api_mobile_post_collect", methods={"POST"})
     * @Authenticated(Authenticated::MOBILE)
     */
    public function postCollect(Request                $request,
                                EntityManagerInterface $manager,
                                AttachmentService      $attachmentService,
                                UniqueNumberService    $uniqueNumberService): JsonResponse {
        $data = json_decode($request->getContent());

        $statusRepository = $manager->getRepository(Status::class);
        $locationRepository = $manager->getRepository(Location::class);
        $clientRepository = $manager->getRepository(Client::class);
        $boxRepository = $manager->getRepository(Box::class);
        $clientOrderRepository = $manager->getRepository(ClientOrder::class);

        $pendingStatus = $statusRepository->findOneBy(['code' => Status::CODE_COLLECT_TRANSIT]);
        $pickLocation = $locationRepository->findOneBy(['name' => $data->location->name]);
        $client = $clientRepository->findOneBy(['name' => $data->location->client]);

        $number = $uniqueNumberService->createUniqueNumber(Collect::class);

        if ($data->data->photo) {
            $photo = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_PHOTO, ["photo", $data->data->photo]);
        }
        $signature = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_SIGNATURE, ["signature", $data->data->signature]);
        $comment = $data->data->comment;

        $crateNumbers = Stream::from($data->crates)->map(fn($crate) => $crate->number)->toArray();
        $crates = $boxRepository->findBy(['number' => $crateNumbers]);

        $collect = (new Collect())
            ->setCreatedAt(new DateTime('now'))
            ->setStatus($pendingStatus)
            ->setTokens((int)$data->token_amount)
            ->setNumber($number)
            ->setPickLocation($pickLocation)
            ->setClient($client)
            ->setPickComment($comment ?? null)
            ->setPickSignature($signature)
            ->setPickPhoto($photo ?? null)
            ->setOperator($this->getUser())
            ->setCrates($crates);

        if (isset($data->clientOrder)) {
            $collect->setClientOrder($clientOrderRepository->find($data->clientOrder));
        }

        $manager->persist($collect);
        $manager->flush();

        return $this->json([
            'success' => true
        ]);
    }

}
