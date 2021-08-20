<?php

namespace App\Controller;

use App\Annotation\Authenticated;
use App\Entity\Attachment;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Collect;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\PreparationLine;
use App\Entity\Quality;
use App\Entity\Preparation;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\FormatHelper;
use App\Service\AttachmentService;
use App\Service\BoxStateService;
use App\Service\ClientOrderService;
use App\Service\DeliveryRoundService;
use App\Service\PreparationService;
use App\Service\UniqueNumberService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;
use App\Service\BoxRecordService;
use App\Service\Mailer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\SnappyResponse;
use Knp\Snappy\Image;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class ApiController extends AbstractController {

    private ?User $user = null;

    public function setUser(?User $user): void {
        $this->user = $user;
    }

    /**
     * @Route("/ping", name="api_ping")
     */
    public function ping(): Response {
        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/kiosk/ping", name="api_kiosk_ping")
     */
    public function kioskPing(): Response {
        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/kiosk/config", name="api_kiosk_config")
     */
    public function config(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        if (isset($content->id)) {
            $kiosk = $manager->getRepository(Location::class)->find($content->id);

            if ($kiosk) {
                if ($kiosk->getMessage()) {
                    $message = $kiosk->getMessage();
                } else {
                    $client = $kiosk->getClient();
                    if ($client && !$client->isMultiSite() && $client->getLinkedMultiSite()) {
                        $client = $client->getLinkedMultiSite();
                    }

                    $message = "{$client->getName()} s'engage avec BoxEaty<br>dans la réduction des déchets";
                }
            }
        }

        return $this->json([
            "phrase" => $message ?? "",
        ]);
    }

    /**
     * @Route("/kiosk/kiosks", name="api_kiosk_kiosks")
     */
    public function kiosks(EntityManagerInterface $manager): Response {
        $kiosks = Stream::from($manager->getRepository(Location::class)->findBy(["kiosk" => true]))
            ->map(fn(Location $kiosk) => $kiosk->serialize())
            ->toArray();

        return $this->json([
            "success" => true,
            "kiosks" => $kiosks,
        ]);
    }

    /**
     * @Route("/kiosk/check-code", name="api_kiosk_check_code")
     */
    public function checkCode(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $page = $manager->getRepository(GlobalSetting::class)->getCorrespondingCode($content->code);

        if ($page) {
            return $this->json([
                "success" => true,
                "page" => $page,
            ]);
        } else {
            return $this->json([
                "success" => false,
            ]);
        }
    }

    /**
     * @Route("/kiosk/kiosks/{kiosk}", name="api_kiosk_get_kiosks", requirements={"kiosk"="\d+"}, methods={"GET"})
     */
    public function kiosk(Location $kiosk): Response {
        if ($kiosk && $kiosk->isKiosk()) {
            return $this->json([
                "success" => true,
                "kiosk" => [
                    "id" => $kiosk->getId(),
                    "name" => $kiosk->getName(),
                    "capacity" => $kiosk->getCapacity(),
                    "client" => null,
                    "boxes" => Stream::from($kiosk->getBoxes())
                        ->map(fn(Box $box) => [
                            "id" => $box->getId(),
                            "number" => $box->getNumber(),
                        ])
                        ->toArray(),
                ]
            ]);
        } else {
            return $this->json([
                "success" => false,
                "message" => "Borne inexistante",
            ]);
        }
    }

    /**
     * @Route("/kiosk/kiosks/empty", name="api_kiosk_empty_kiosk", options={"expose": true})
     */
    public function emptyKiosk(Request $request,
                               BoxRecordService $boxRecordService,
                               EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk ?? $request->request->get("id"));

        foreach ($kiosk->getBoxes() as $box) {
            $user = $this->getUser();
            $oldLocation = $box->getLocation();
            $oldState = $box->getState();
            $oldComment = $box->getComment();

            $box->setState(BoxStateService::STATE_BOX_UNAVAILABLE)
                ->setLocation($kiosk->getDeporte())
                ->setComment($content->comment ?? null);

            [$tracking, $record] = $boxRecordService->generateBoxRecords(
                $box,
                [
                    'location' => $oldLocation,
                    'state' => $oldState,
                    'comment' => $oldComment
                ],
                $user instanceof User ? $user : null
            );

            if ($tracking) {
                $tracking->setBox($box);
                $manager->persist($tracking);
            }

            if ($record) {
                $record->setBox($box);
                $manager->persist($record);
            }
        }

        $manager->flush();

        return $this->json([
            "success" => true,
            'kiosk' => $kiosk->serialize()
        ]);
    }

    /**
     * @Route("/kiosk/box/retrieve", name="api_kiosk_retrieve_box")
     */
    public function retrieveBox(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => BoxStateService::STATE_BOX_CONSUMER,
        ]);

        if ($box && $box->getType() && $box->getOwner()) {
            return $this->json([
                "success" => true,
                "box" => [
                    "id" => $box->getId(),
                    "number" => $box->getNumber(),
                ],
            ]);
        } else {
            return $this->json([
                "success" => false,
            ]);
        }
    }

    /**
     * @Route("/kiosk/box/drop", name="api_kiosk_drop_box")
     */
    public function dropBox(Request $request,
                            BoxRecordService $boxRecordService,
                            EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);
        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => BoxStateService::STATE_BOX_CONSUMER,
        ]);

        if ($box
            && $box->getType()
            && $box->getOwner()) {
            $oldLocation = $box->getLocation();
            $oldState = $box->getState();
            $oldComment = $box->getComment();

            $kiosk->setDeposits($kiosk->getDeposits() + 1);;

            $box
                ->setCanGenerateDepositTicket(true)
                ->setUses($box->getUses() + 1)
                ->setState(BoxStateService::STATE_BOX_UNAVAILABLE)
                ->setLocation($kiosk)
                ->setComment($content->comment ?? null);

            [$tracking, $record] = $boxRecordService->generateBoxRecords(
                $box,
                [
                    'location' => $oldLocation,
                    'state' => $oldState,
                    'comment' => $oldComment
                ],
                null
            );

            if ($tracking) {
                $tracking->setBox($box);
                $manager->persist($tracking);
            }

            if ($record) {
                $record->setBox($box);
                $manager->persist($record);
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "box" => [
                    "id" => $box->getId(),
                    "number" => $box->getNumber(),
                ],
                'kiosk' => $kiosk->serialize()
            ]);
        } else {
            return $this->json([
                "success" => false,
                "message" => "La Box n'existe pas, veuillez contacter un responsable d'établissement.",
            ]);
        }
    }

    /**
     * @Route("/kiosk/deposit-ticket/statistics", name="api_kiosk_deposit_ticket_statistics")
     */
    public function depositTicketStatistics(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $locationRepository = $manager->getRepository(Location::class);
        $kiosk = $locationRepository->find($content->id);
        $totalDeposits = $locationRepository->getTotalDeposits();

        $lessWaste = $kiosk->getDeposits() * 32;
        if ($lessWaste > 1000) {
            $prefix = "kg";
            $lessWaste = round($lessWaste / 1000);
        } else if ($lessWaste > 1000000) {
            $prefix = "t";
            $lessWaste = round($lessWaste / 1000000);
        } else {
            $prefix = "g";
        }

        return $this->json([
            "collectedBoxes" => $kiosk->getDeposits(),
            "lessWaste" => "$lessWaste $prefix",
            "totalPackagingAvoided" => $totalDeposits,
        ]);
    }

    /**
     * @Route("/kiosk/deposit-ticket/mail", name="api_kiosk_deposit_ticket_mail")
     */
    public function mailDepositTicket(Request $request, EntityManagerInterface $manager, Mailer $mailer): Response {
        $content = json_decode($request->getContent());

        $ticket = $this->createDepositTicket($content);
        if ($ticket instanceof Response) {
            return $ticket;
        }

        $client = $ticket->getLocation() ? $ticket->getLocation()->getClient() : null;
        $clients = $client ? $client->getDepositTicketsClients() : [];

        if (count($clients) === 0) {
            $usable = "tout le réseau BoxEaty";
        } else if (count($clients) === 1) {
            $usable = "le restaurant <span class='no-wrap'>{$clients[0]->getName()}</span>";
        } else {
            $usable = "les restaurants " . Stream::from($clients)
                    ->map(fn(Client $client) => $client->getName())
                    ->map(fn(string $client) => "<span class='no-wrap'>$client</span>")
                    ->join(", ");
        }

        $mailer->send(
            $content->email,
            "BoxEaty - Ticket‑consigne",
            $this->renderView("emails/deposit_ticket.html.twig", [
                "ticket" => $ticket,
                "usable" => $usable,
            ])
        );

        $manager->persist($ticket);
        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/kiosk/deposit-ticket/print", name="api_kiosk_deposit_ticket_print")
     */
    public function depositTicketPrint(Request $request): Response {
        $ticket = $this->createDepositTicket(json_decode($request->getContent()));
        if ($ticket instanceof Response) {
            return $ticket;
        }

        return $this->json([
            "success" => true,
            "ticket" => $ticket->getId(),
        ]);
    }

    /**
     * @Route("/kiosk/deposit-ticket/image/{ticket}", name="api_kiosk_deposit_ticket_image")
     */
    public function depositTicketImage(Image $snappy, DepositTicket $ticket): Response {
        $client = $ticket->getLocation() ? $ticket->getLocation()->getClient() : null;
        $clients = $client ? $client->getDepositTicketsClients() : [];

        if (count($clients) === 0) {
            $usable = "tout le réseau BoxEaty";
        } else if (count($clients) === 1) {
            $usable = "le restaurant <span class='no-wrap'>{$clients[0]->getName()}</span>";
        } else {
            $usable = "les restaurants " . Stream::from($clients)
                    ->map(fn(Client $client) => $client->getName())
                    ->map(fn(string $client) => "<span class='no-wrap'>$client</span>")
                    ->join(", ");
        }

        $html = $this->renderView("print/deposit_ticket.html.twig", [
            "ticket" => $ticket,
            "usable" => $usable,
        ]);

        $image = $snappy->getOutputFromHtml($html, [
            "format" => "png",
            "disable-javascript" => true,
            "disable-smart-width" => true,
            "width" => 600,
            "zoom" => 2,
        ]);

        return new SnappyResponse($image, "deposit-ticket.png", "image/png", "inline");
    }

    private function createDepositTicket($content) {
        $manager = $this->getDoctrine()->getManager();
        $box = $manager->getRepository(Box::class)->find($content->box);
        $validity = $manager->getRepository(Location::class)->find($content->kiosk)
            ->getClient()
            ->getDepositTicketValidity();

        if (!$box->getCanGenerateDepositTicket()) {
            return $this->json([
                "success" => false,
                "message" => "Cette Box n'a pas été déposée",
            ]);
        }

        $depositTicket = (new DepositTicket())
            ->setBox($box)
            ->setNumber(StringHelper::random(5))
            ->setState(DepositTicket::VALID)
            ->setLocation($box->getLocation())
            ->setCreationDate(new DateTime())
            ->setValidityDate(new DateTime("+$validity month"));

        $box->setCanGenerateDepositTicket(false);

        $manager->persist($depositTicket);
        $manager->flush();

        return $depositTicket;
    }

    /**
     * @Route("/mobile/login", name="api_mobile_login")
     */
    public function login(Request $request, EntityManagerInterface $manager, UserPasswordHasherInterface $hasher): Response {
        $content = json_decode($request->getContent());

        $user = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($user && $hasher->isPasswordValid($user, $content->password)) {
            $user->setApiKey(bin2hex(random_bytes(16)));
            $manager->flush();

            return $this->json([
                "success" => true,
                "token" => $user->getApiKey(),
            ]);
        }

        return $this->json([
            "success" => false,
            "message" => "Identifiants invalides"
        ]);
    }

    /**
     * @Route("/mobile/depositories", name="api_mobile_depositories")
     * @Authenticated()
     */
    public function depositories(EntityManagerInterface $manager): Response {
        return $this->json($manager->getRepository(Depository::class)->getAll());
    }

    /**
     * @Route("/mobile/delivery-rounds", name="api_mobile_delivery_rounds")
     * @Authenticated()
     */
    public function deliveryRounds(EntityManagerInterface $manager): Response {
        $now = new DateTime("today midnight");
        $rounds = $manager->getRepository(DeliveryRound::class)->findAwaitingDeliverer($this->user);

        $serialized = Stream::from($rounds)
            ->map(fn(DeliveryRound $round) => [
                "id" => $round->getId(),
                "number" => $round->getNumber(),
                "status" => $round->getStatus()->getCode(),
                "depository" => FormatHelper::named($round->getDepository()),
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
                    "delivered" => $order->isOnStatusCode(Status::CODE_ORDER_FINISHED),
                    "crate_amount" => $order->getPreparation() ? $order->getPreparation()->getLines()->count() : -1,
                    "token_amount" => $order->getTokensAmount(),
                    "preparation" => $order->getPreparation() ? [
                        "id" => $order->getPreparation()->getId(),
                        "depository" => FormatHelper::named($order->getPreparation()->getDepository()),
                        "lines" => $order->getPreparation()->getLines()->map(fn(PreparationLine $line) => [
                            "crate" => $line->getCrate()->getNumber(),
                            "type" => FormatHelper::named($line->getCrate()->getType()),
                            "taken" => $line->isTaken(),
                            "deposited" => $line->isDeposited(),
                        ]),
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
     * @Route("/mobile/deliveries/start", name="api_mobile_deliveries_start")
     * @Authenticated()
     */
    public function deliveryStart(EntityManagerInterface $manager, Request $request, ClientOrderService $clientOrderService): Response {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);

        if ($order) {
            $statusRepository = $manager->getRepository(Status::class);

            $orderTransitStatus = $statusRepository->findOneBy(['code' => Status::CODE_ORDER_TRANSIT]);
            $history = $clientOrderService->updateClientOrderStatus($order, $orderTransitStatus, $this->getUser());
            $manager->persist($history);

            $order->getDelivery()->setStatus($statusRepository->findOneBy(['code' => Status::CODE_DELIVERY_TRANSIT]));

            $manager->flush();

            return $this->json([
                "success" => true,
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/mobile/deliveries/take", name="api_mobile_deliveries_take")
     * @Authenticated()
     */
    public function deliveryTake(EntityManagerInterface $manager, Request $request, BoxRecordService $service): Response {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);
        $crate = $manager->getRepository(Box::class)->findOneBy(["number" => $data->crate]);

        if ($crate) {
            $line = $order->getPreparation()
                ->getLines()
                ->filter(fn(PreparationLine $line) => $line->getCrate()->getNumber() === $crate->getNumber())
                ->first();

            $line->setTaken(true);

            $previous = $crate->getLocation();
            $location = $previous ? $previous->getDeporte() : null;

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                if ($location) {
                    $box->setLocation($location);
                }

                [$tracking] = $service->generateBoxRecords($box, [
                    "location" => $previous,
                ], $this->user);

                if ($tracking) {
                    $manager->persist($tracking);
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
     * @Route("/mobile/deliveries/deposit", name="api_mobile_deliveries_deposit")
     * @Authenticated()
     */
    public function deliveryDeposit(EntityManagerInterface $manager, Request $request, BoxRecordService $service): Response {
        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);
        $crate = $manager->getRepository(Box::class)->findOneBy(["number" => $data->crate]);

        if ($crate) {
            $line = $order->getPreparation()
                ->getLines()
                ->filter(fn(PreparationLine $line) => $line->getCrate()->getNumber() === $crate->getNumber())
                ->first();

            $line->setDeposited(true);

            $previous = $crate->getLocation();
            $location = $order->getClient()->getLocations()
                ->filter(fn(Location $location) => $location->getType() === Location::RECEPTION)
                ->first();

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                if ($location) {
                    $box->setLocation($location)
                        ->setState(BoxStateService::STATE_BOX_CLIENT);
                }

                [$tracking] = $service->generateBoxRecords($box, [
                    "location" => $previous,
                ], $this->user);

                if ($tracking) {
                    $manager->persist($tracking);
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
     * @Route("/mobile/deliveries/finish", name="api_mobile_deliveries_finish")
     * @Authenticated
     */
    public function finishDelivery(EntityManagerInterface $manager,
                                   Request $request,
                                   AttachmentService $attachmentService,
                                   ClientOrderService $clientOrderService): Response {

        $data = json_decode($request->getContent());
        $order = $manager->getRepository(ClientOrder::class)->find($data->order);

        if ($order) {
            $deliveryRound = $order->getDeliveryRound();
            $delivery = $order->getDelivery();

            $orderStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_ORDER_FINISHED]);
            $deliveryStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_DELIVERY_DELIVERED]);
            $signature = $attachmentService->createAttachment(Attachment::TYPE_DELIVERY_SIGNATURE, ["signature", $data->signature]);
            $photo = $attachmentService->createAttachment(Attachment::TYPE_DELIVERY_PHOTO, ["photo", $data->photo]);

            $history = $clientOrderService->updateClientOrderStatus($order, $orderStatus, $this->getUser());
            $manager->persist($history);

            $order->setComment($data->comment);

            $delivery->setDistance($data->distance)
                ->setStatus($deliveryStatus)
                ->setSignature($signature)
                ->setPhoto($photo);

            $unfinishedDeliveries = $deliveryRound->getOrders()
                ->filter(fn(ClientOrder $order) => !$order->isOnStatusCode(Status::CODE_ORDER_FINISHED))
                ->count();

            if ($unfinishedDeliveries === 0) {
                $status = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_ROUND_FINISHED]);
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
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/mobile/preparations", name="api_mobile_preparations")
     * @Authenticated
     */
    public function preparations(EntityManagerInterface $manager, Request $request): Response {
        $depositoryRepository = $manager->getRepository(Depository::class);
        $preparationRepository = $manager->getRepository(Preparation::class);

        $depositoryId = $request->query->get('depository');
        $depository = $depositoryId
            ? $depositoryRepository->find($depositoryId)
            : null;

        $preparations = $preparationRepository->getByDepository($depository, $this->user);

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
     * @Route("/mobile/locations", name="api_mobile_locations")
     * @Authenticated
     */
    public function locations(EntityManagerInterface $manager): Response {
        return $this->json($manager->getRepository(Location::class)->getAll());
    }

    /**
     * @Route("/mobile/qualities", name="api_mobile_qualities")
     * @Authenticated
     */
    public function qualities(EntityManagerInterface $manager): Response {
        return $this->json($manager->getRepository(Quality::class)->getAll());
    }

    /**
     * @Route("/mobile/crates", name="api_mobile_crates")
     * @Authenticated
     */
    public function crates(EntityManagerInterface $manager, Request $request): Response {
        $depository = $manager->getRepository(Depository::class)->find($request->query->get('depository'));
        return $this->json($manager->getRepository(Box::class)->getByDepository($depository));
    }

    /**
     * @Route("/mobile/box", name="api_mobile_box")
     * @Authenticated
     */
    public function box(EntityManagerInterface $manager, Request $request): Response {
        return $this->json($manager->getRepository(Box::class)->getByNumber($request->query->get('box')));
    }

    /**
     * @Route("/mobile/reverse-tracking", name="api_mobile_reverse_tracking")
     * @Authenticated
     */
    public function reverseTracking(EntityManagerInterface $manager, Request $request, BoxRecordService $boxRecordService): Response {

        $boxRepository = $manager->getRepository(Box::class);
        $locationRepository = $manager->getRepository(Location::class);
        $qualityRepository = $manager->getRepository(Quality::class);

        $args = json_decode($request->getContent(), true);
        $args['boxes'] = explode(',', $args['boxes']);
        /**
         * @var $boxes Box[]
         */
        $boxes = [];

        foreach ($args['boxes'] as $box) {
            $boxes[] = $boxRepository->find($box);
        }
        $boxes[] = $boxRepository->findOneBy(['number' => $args['crate']]);
        $chosenQuality = $qualityRepository->find($args['quality']);
        $chosenLocation = $locationRepository->find($args['location']);
        foreach ($boxes as $box) {
            $box
                ->setLocation($chosenLocation)
                ->setQuality($chosenQuality);
            $record = $boxRecordService->createBoxRecord($box, true);
            $record
                ->setBox($box)
                ->setState(BoxStateService::STATE_RECORD_IDENTIFIED)
                ->setUser($this->user);
            $manager->persist($record);
        }
        $manager->flush();
        return $this->json([]);
    }

    /**
     * @Route("/mobile/preparations/{preparation}", name="api_mobile_get_preparation", methods={"GET"})
     * @Authenticated
     */
    public function getPreparation(EntityManagerInterface $entityManager,
                                   ClientOrderService     $clientOrderService,
                                   Preparation            $preparation): Response {
        if ((
                !$preparation->isOnStatusCode(Status::CODE_PREPARATION_TO_PREPARE)
                && !$preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARING)
            )
            || (
                $preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARING)
                && $preparation->getOperator()
                && $this->user !== $preparation->getOperator()
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
     * @Route("/mobile/preparations/{preparation}", name="api_mobile_patch_preparation", methods={"PATCH"})
     * @Authenticated
     */
    public function patchPreparation(Request                $request,
                                     Preparation            $preparation,
                                     PreparationService     $preparationService,
                                     DeliveryRoundService   $deliveryRoundService,
                                     BoxRecordService       $boxRecordService,
                                     EntityManagerInterface $entityManager,
                                     Mailer                 $mailer): Response {

        $content = json_decode($request->getContent(), true);

        $preparing = $content['preparing'] ?? false;
        $statusRepository = $entityManager->getRepository(Status::class);

        if ($preparing) {
            if ($preparation->isOnStatusCode(Status::CODE_PREPARATION_TO_PREPARE)) {
                $status = $statusRepository->findOneBy(['code' => Status::CODE_PREPARATION_PREPARING]);

                $preparation
                    ->setStatus($status)
                    ->setOperator($this->user);

                $entityManager->flush();
                return $this->json([
                    'success' => true,
                    'message' => 'La préparation a été réservée'
                ]);
            }
            else if (!$preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARING)
                || $this->user !== $preparation->getOperator()){
                return $this->json([
                    'success' => false,
                    'message' => 'La préparation est en cours de préparation par un autre utilisateur'
                ]);
            }
            else {
                // $preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARING)
                // AND $this->user === $preparation->getOperator()
                return $this->json([
                    'success' => true
                ]);
            }

        }
        else if ($preparation->isOnStatusCode(Status::CODE_PREPARATION_PREPARING)
                 && $this->user === $preparation->getOperator()) {
            $userRepository = $entityManager->getRepository(User::class);

            $preparedStatus = $statusRepository->findOneBy(['code' => Status::CODE_PREPARATION_PREPARED]);
            $preparedDeliveryStatus = $statusRepository->findOneBy(['code' => Status::CODE_DELIVERY_PREPARED]);

            $crates = $content['crates'] ?? [];

            $clientOrder = $preparation->getOrder();
            $result = $preparationService->handlePreparedCrates($entityManager, $clientOrder, $crates);

            $date = new DateTime();
            $user = $this->user;

            if ($result['success']) {
                foreach ($result['entities'] as $crateData) {
                    $preparationLine = new PreparationLine();
                    $preparationLine
                        ->setPreparation($preparation)
                        ->setCrate($crateData['crate']);
                    $entityManager->persist($preparationLine);

                    foreach ($crateData['boxes'] as $box) {
                        $olderValues = [
                            'location' => $box->getLocation(),
                            'state' => $box->getState(),
                            'comment' => $box->getComment()
                        ];

                        $box
                            ->setLocation($box->getLocation()->getDeporte())
                            ->setState(BoxStateService::STATE_BOX_UNAVAILABLE);

                        [$tracking, $record] = $boxRecordService->generateBoxRecords($box, $olderValues, $user, $date);

                        if ($tracking) {
                            $tracking->setBox($box);
                            $entityManager->persist($tracking);
                        }

                        if ($record) {
                            $record->setBox($box);
                            $entityManager->persist($record);
                        }

                        $preparationLine->addBox($box);
                    }
                }

                $preparation->setStatus($preparedStatus);

                $delivery = $clientOrder->getDelivery();
                if(isset($delivery)) {
                    $delivery->setStatus($preparedDeliveryStatus);
                }

                $deliveryRound = $clientOrder->getDeliveryRound();
                if ($deliveryRound) {
                    $deliveryRoundService->updateDeliveryRound($entityManager, $deliveryRound);
                }

                $entityManager->flush();

                $users = $userRepository->findBy(['deliveryAssignmentPreparationMail' => 1]);
                if(!empty($users)) {
                    $mailer->send(
                        $users,
                        "BoxEaty - Affectation de tournée",
                        $this->renderView("emails/delivery_round.html.twig", [
                            "expectedDelivery" => $preparation->getOrder()->getExpectedDelivery(),
                            "deliveryRound" => $preparation->getOrder()->getDeliveryRound()
                        ])
                    );
                }
                return $this->json([
                    'success' => true,
                ]);
            }
            else {
                return $this->json([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
        }

        throw new NotFoundHttpException();
    }

    /**
     * @Route("/mobile/available-crates", name="api_mobile_available_crates")
     * @Authenticated
     */
    public function availableCrates(EntityManagerInterface $manager, Request $request): Response {
        $crateType = $manager->getRepository(BoxType::class)->findOneBy(['name' => $request->query->get('type')]);
        $crates = $crateType->getCrates();

        $availableCrates = [];
        /** @var Box $crate */
        foreach ($crates as $crate) {
            if ($crate->getLocation()) {
                $location = $crate->getLocation()->getName();
                $number = $crate->getNumber();
                $availableCrates[] = [
                    'number' => $number,
                    'id' => $crate->getId(),
                    'type' => $crate->getType()->getName(),
                    'location' => $location
                ];
            }
        }

        return $this->json($availableCrates);
    }

    /**
     * @Route("/mobile/boxes", name="api_mobile_available_boxes")
     * @Authenticated
     */
    public function availableBoxes(EntityManagerInterface $manager, Request $request): Response {
        $query = $request->query;
        $preparation = $manager->getRepository(Preparation::class)->find($query->get('preparation'));

        $boxTypes = Stream::from($preparation->getOrder()->getLines())
            ->map(fn(ClientOrderLine $line) => [
                $line->getBoxType()->getId()
            ])
            ->toArray();

        $boxes = $manager->getRepository(Box::class)->getAvailableAndCleanedBoxByType($boxTypes);

        $availableBoxes = [];
        foreach ($boxes as $box) {
            if ($box->getLocation() && $box->getType()) {
                $type = $box->getType()->getName();
                $location = $box->getLocation()->getName();
                $number = $box->getNumber();

                $availableBoxes[] = [
                    'type' => $type,
                    'location' => $location,
                    'number' => $number
                ];
            }
        }

        return $this->json([
            'availableBoxes' => $availableBoxes,
        ]);
    }

    /**
     * @Route("/mobile/box-informations", name="api_mobile_box_informations")
     * @Authenticated
     */
    public function boxInformations(EntityManagerInterface $manager, Request $request): Response {
        $box = $request->query->get('box');
        $isCrate = $request->query->get('isCrate');
        $crate = $request->query->get('crate');


        $box = $manager->getRepository(Box::class)->findOneBy(['number' => $box, 'isBox' => $isCrate ? 0 : 1]);
        if ($crate) {
            $crate = $manager->getRepository(Box::class)->findOneBy(['number' => $crate]);
        }

        if ($box) {
            $type = $box->getType()->getName();
            $volume = $box->getType()->getVolume();
            $number = $box->getNumber();

            return $this->json([
                'success' => true,
                'data' => [
                    'number' => $number,
                    'type' => $type,
                    'volume' => $volume,
                    'crateVolume' => $crate ? $crate->getType()->getVolume() : 0
                ]
            ]);
        }

        return $this->json([
            "success" => false,
            "message" => "La box n'existe pas",
        ]);
    }

    /**
     * @Route("/mobile/moving", name="api_mobile_moving")
     * @Authenticated
     */
    public function moving(EntityManagerInterface $manager, Request $request, BoxRecordService $boxRecordService): Response {

        $boxRepository = $manager->getRepository(Box::class);
        $locationRepository = $manager->getRepository(Location::class);
        $qualityRepository = $manager->getRepository(Quality::class);

        $args = json_decode($request->getContent(), true);
        $scannedBoxesAndCrates = Stream::from($args['scannedBoxesAndCrates'])->map(fn($box) => $box['number'])->toArray();

        $boxes = [];
        foreach ($scannedBoxesAndCrates as $scannedBoxOrCrate) {
            $boxes[] = $boxRepository->findOneBy(['number' => $scannedBoxOrCrate]);
        }

        $chosenQuality = $qualityRepository->find($args['quality']);
        $chosenLocation = $locationRepository->find($args['location']);
        foreach ($boxes as $box) {
            $box
                ->setLocation($chosenLocation)
                ->setQuality($chosenQuality);

            $record = $boxRecordService->createBoxRecord($box, true);
            $record
                ->setBox($box)
                ->setState(BoxStateService::STATE_RECORD_IDENTIFIED)
                ->setUser($this->user);

            $manager->persist($record);
        }

        $manager->flush();
        return $this->json([]);
    }

    /**
     * @Route("/mobile/pending-collects", name="api_mobile_pending_collects")
     * @Authenticated
     */
    public function collects(EntityManagerInterface $manager) {
        $pendingCollects = $manager->getRepository(Collect::class)->getPendingCollects();

        return $this->json($pendingCollects);
    }

    /**
     * @Route("/mobile/collect-crates/{collect}", name="api_mobile_collect_crates")
     * @Authenticated
     */
    public function collectCrates(Collect $collect) {
        $collectCrates = Stream::from($collect->getCrates()->map(fn(Box $crate) => [
            'number' => $crate->getNumber(),
            'type' => $crate->getType()->getName()
        ]));

        return $this->json($collectCrates);
    }

    /**
     * @Route("/mobile/collect-validate", name="api_mobile_collect_validate")
     * @Authenticated
     */
    public function collectValidate(Request $request, EntityManagerInterface $manager,
                                    AttachmentService $attachmentService, BoxRecordService $boxRecordService)
    {
        $data = json_decode($request->getContent());

        $collect = $manager->find(Collect::class, $data->collect);

        if ($collect) {
            $dropLocation = $manager->find(Location::class, $data->drop_location);
            $crates = $collect->getCrates();

            foreach ($crates as $crate) {
                $user = $this->getUser();
                $oldLocation = $crate->getLocation();
                $oldState = $crate->getState();
                $oldComment = $crate->getComment();

                $crate
                    ->setState(BoxStateService::STATE_RECORD_PACKING)
                    ->setLocation($dropLocation);

                [$tracking, $record] = $boxRecordService->generateBoxRecords(
                    $crate,
                    [
                        'location' => $oldLocation,
                        'state' => $oldState,
                        'comment' => $oldComment
                    ],
                    $user instanceof User ? $user : null
                );

                if ($tracking) {
                    $tracking->setBox($crate);
                    $manager->persist($tracking);
                }

                if ($record) {
                    $record->setBox($crate);
                    $manager->persist($record);
                }
            }

            $collectStatus = $manager->getRepository(Status::class)->findByCode(Status::CODE_COLLECT_FINISHED);

            if($data->data->photo) {
                $photo = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_PHOTO, ["photo", $data->data->photo]);
            }
            $signature = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_SIGNATURE, ["signature", $data->data->signature]);
            $comment = $data->data->comment;

            $collect
                ->setStatus($collectStatus)
                ->setSignature($signature)
                ->setPhoto($photo ?? null)
                ->setComment($comment ?? null)
                ->setTreatedAt(new DateTime('now'))
                ->setTokens((int)$data->token_amount);

            $manager->flush();

            return $this->json([
                "success" => true
            ]);
        }

        throw new BadRequestHttpException();
    }

    /**
     * @Route("/mobile/location", name="api_mobile_location")
     * @Authenticated
     */
    public function location(Request $request, EntityManagerInterface $manager)
    {
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
     * @Route("/mobile/collect-new-validate", name="api_mobile_collect_new_validate")
     * @Authenticated
     */
    public function collectNewValidate(Request $request, EntityManagerInterface $manager,
                                       AttachmentService $attachmentService,
                                       UniqueNumberService $uniqueNumberService): Response
    {
        $data = json_decode($request->getContent());

        $pendingStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_COLLECT_TRANSIT]);
        $pickLocation = $manager->getRepository(Location::class)->findOneBy(['name' => $data->location->name]);
        $client = $manager->getRepository(Client::class)->findOneBy(['name' => $data->location->client]);

        $number = $uniqueNumberService->createUniqueNumber($manager, Collect::PREFIX_NUMBER, Collect::class);

        if($data->data->photo) {
            $photo = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_PHOTO, ["photo", $data->data->photo]);
        }
        $signature = $attachmentService->createAttachment(Attachment::TYPE_COLLECT_SIGNATURE, ["signature", $data->data->signature]);
        $comment = $data->data->comment;

        $crateNumbers = Stream::from($data->crates)->map(fn($crate) => $crate->number)->toArray();
        $crates = $manager->getRepository(Box::class)->findBy(['number' => $crateNumbers]);

        $collect = (new Collect())
            ->setCreatedAt(new DateTime('now'))
            ->setStatus($pendingStatus)
            ->setTokens((int) $data->token_amount)
            ->setNumber($number)
            ->setPickLocation($pickLocation)
            ->setClient($client)
            ->setComment($comment ?? null)
            ->setSignature($signature)
            ->setPhoto($photo ?? null)
            ->setOperator($this->user)
            ->setCrates($crates);

        $manager->persist($collect);
        $manager->flush();

        return $this->json([]);
    }

}
