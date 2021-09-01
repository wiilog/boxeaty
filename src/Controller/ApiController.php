<?php

namespace App\Controller;

use App\Annotation\Authenticated;
use App\Entity\Attachment;
use App\Entity\Box;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\Collect;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
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
use Knp\Bundle\SnappyBundle\Snappy\Response\SnappyResponse;
use Knp\Snappy\Image;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use WiiCommon\Helper\Stream;
use WiiCommon\Helper\StringHelper;

/**
 * @Route("/api")
 */
class ApiController extends AbstractController {

    private ?User $user = null;

    public function getUser(): ?User {
        return $this->user ?? parent::getUser();
    }

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
    public function emptyKiosk(Request                $request,
                               BoxRecordService       $boxRecordService,
                               EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk ?? $request->request->get("id"));

        foreach ($kiosk->getBoxes() as $box) {
            $previous = clone $box;

            $box->setState(BoxStateService::STATE_BOX_UNAVAILABLE)
                ->setLocation($kiosk->getOffset())
                ->setComment($content->comment ?? null);

            [$tracking, $record] = $boxRecordService->generateBoxRecords(
                $box, $previous, $this->getUser()
            );

            $boxRecordService->persist($box, $tracking);
            $boxRecordService->persist($box, $record);
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
    public function dropBox(Request                $request,
                            BoxRecordService       $boxRecordService,
                            EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);
        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => BoxStateService::STATE_BOX_CONSUMER,
        ]);

        if ($box && $box->getType() && $box->getOwner()) {
            $previous = clone $box;

            $kiosk->setDeposits($kiosk->getDeposits() + 1);;

            $box->setCanGenerateDepositTicket(true)
                ->setUses($box->getUses() + 1)
                ->setState(BoxStateService::STATE_BOX_UNAVAILABLE)
                ->setLocation($kiosk)
                ->setComment($content->comment ?? null);

            [$tracking, $record] = $boxRecordService->generateBoxRecords($box, $previous);
            $boxRecordService->persist($box, $tracking);
            $boxRecordService->persist($box, $record);

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
     * @Route("/mobile/deliveries/start", name="api_mobile_deliveries_start")
     * @Authenticated()
     */
    public function deliveryStart(EntityManagerInterface $manager, Request $request, ClientOrderService $clientOrderService): Response {
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

            $offset = $crate->getLocation() ? $crate->getLocation()->getOffset() : null;

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                $previous = clone $box;
                $box->setLocation($offset);

                [$tracking, $record] = $service->generateBoxRecords($box, $previous, $this->getUser());
                $service->persist($box, $tracking);
                $service->persist($box, $record);
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

            $location = $order->getClient()->getLocations()
                ->filter(fn(Location $location) => $location->getType() === Location::RECEPTION)
                ->first();

            foreach (Stream::from([$crate], $crate->getContainedBoxes()) as $box) {
                $previous = clone $box;
                $box->setLocation($location)
                    ->setState(BoxStateService::STATE_BOX_CLIENT);

                [$tracking, $record] = $service->generateBoxRecords($box, $previous, $this->getUser());
                $service->persist($box, $tracking);
                $service->persist($box, $record);
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
                                   Request                $request,
                                   AttachmentService      $attachmentService,
                                   ClientOrderService     $clientOrderService): Response {

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

            [$tracking, $record] = $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
            $boxRecordService->persist($box, $tracking->setState(BoxStateService::STATE_RECORD_IDENTIFIED));
            $boxRecordService->persist($box, $record->setState(BoxStateService::STATE_RECORD_IDENTIFIED));
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/mobile/preparations/{preparation}", name="api_mobile_get_preparation", methods={"GET"})
     * @Authenticated
     */
    public function getPreparation(EntityManagerInterface $entityManager,
                                   ClientOrderService     $clientOrderService,
                                   Preparation            $preparation): Response {
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
     * @Route("/mobile/preparations/{preparation}", name="api_mobile_patch_preparation", methods={"PATCH"})
     * @Authenticated
     */
    public function patchPreparation(Request                $request,
                                     Preparation            $preparation,
                                     PreparationService     $preparationService,
                                     DeliveryRoundService   $deliveryRoundService,
                                     BoxRecordService       $boxRecordService,
                                     ClientOrderService     $clientOrderService,
                                     EntityManagerInterface $entityManager,
                                     Mailer                 $mailer): Response {

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
            $userRepository = $entityManager->getRepository(User::class);

            $preparedStatus = $statusRepository->findOneBy(["code" => Status::CODE_PREPARATION_PREPARED]);
            $preparedDeliveryStatus = $statusRepository->findOneBy(["code" => Status::CODE_DELIVERY_AWAITING_DELIVERER]);
            $preparedOrderStatus = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_PREPARED]);
            $awaitingDelivererStatus = $statusRepository->findOneBy(["code" => Status::CODE_ORDER_AWAITING_DELIVERER]);

            $crates = $content['crates'] ?? [];

            $clientOrder = $preparation->getOrder();
            $result = $preparationService->handlePreparedCrates($entityManager, $clientOrder, $crates);

            $date = new DateTime();
            $user = $this->getUser();

            if ($result['success']) {
                foreach ($result['entities'] as $crateData) {
                    $preparationLine = new PreparationLine();
                    $preparationLine
                        ->setPreparation($preparation)
                        ->setCrate($crateData['crate']);
                    $entityManager->persist($preparationLine);

                    foreach ($crateData['boxes'] as $box) {
                        $previous = clone $box;
                        $box->setCrate($crateData['crate'])
                            ->setLocation($box->getLocation()->getOffset())
                            ->setState(BoxStateService::STATE_BOX_UNAVAILABLE);

                        [$tracking, $record] = $boxRecordService->generateBoxRecords($box, $previous, $user, $date);
                        $boxRecordService->persist($box, $tracking);
                        $boxRecordService->persist($box, $record);

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

                $users = $userRepository->findBy(['deliveryAssignmentPreparationMail' => 1]);
                if (!empty($users)) {
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
            } else {
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
     * @Route("/mobile/available-boxes", name="api_mobile_get_boxes")
     * @Authenticated
     */
    public function availableBoxes(EntityManagerInterface $manager, Request $request): Response {
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
     * @Route("/mobile/box-informations", name="api_mobile_box_informations")
     * @Authenticated
     */
    public function boxInformations(EntityManagerInterface $manager, Request $request): Response {
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
                'success' => true,
                'data' => [
                    'number' => $number,
                    'type' => $type,
                    'volume' => $volume,
                    'crateVolume' => $crate ? $crate->getType()->getVolume() : 0
                ]
            ]);
        }

        return $this->json(false);
    }

    /**
     * @Route("/mobile/moving", name="api_mobile_moving")
     * @Authenticated
     */
    public function moving(EntityManagerInterface $manager, Request $request, BoxRecordService $boxRecordService): Response {
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

                [$tracking, $record] = $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
                $boxRecordService->persist($box, $tracking->setState(BoxStateService::STATE_RECORD_IDENTIFIED));
                $boxRecordService->persist($box, $record->setState(BoxStateService::STATE_RECORD_IDENTIFIED));
            }
        }

        $manager->flush();
        return $this->json([]);
    }

    /**
     * @Route("/mobile/collects", name="api_mobile_get_collects", methods={"GET"})
     * @Authenticated
     */
    public function getCollects(EntityManagerInterface $manager) {
        return $this->json($manager->getRepository(Collect::class)->getPendingCollects($this->getUser()));
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
     * @Route("/mobile/collects/{collect}", name="api_mobile_patch_collect", methods={"PATCH"})
     * @Authenticated
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

                [$tracking, $record] = $boxRecordService->generateBoxRecords($crate, $previous, $this->getUser());
                $boxRecordService->persist($crate, $tracking);
                $boxRecordService->persist($crate, $record);
            }

            $collectStatus = $manager->getRepository(Status::class)->findOneBy(['code' => Status::CODE_COLLECT_FINISHED]);

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
    public function location(Request $request, EntityManagerInterface $manager) {
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
     * @Route("/mobile/collects", name="api_mobile_post_collect", methods={"POST"})
     * @Authenticated
     */
    public function postCollect(Request                $request,
                                EntityManagerInterface $manager,
                                AttachmentService      $attachmentService,
                                UniqueNumberService    $uniqueNumberService): Response {
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

        $clientOrderId = $data->clientOrder ?? null;

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


        if ($clientOrderId) {
            $clientOrder = $clientOrderRepository->find($clientOrderId);
            $collect
                ->setClientOrder($clientOrder);
        }

        $manager->persist($collect);
        $manager->flush();

        return $this->json([
            'success' => true
        ]);
    }

}
