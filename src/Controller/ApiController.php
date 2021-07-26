<?php

namespace App\Controller;

use App\Annotation\Authenticated;
use App\Entity\Box;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\ClientOrderLine;
use App\Entity\DeliveryRound;
use App\Entity\Depository;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Preparation;
use App\Entity\User;
use App\Helper\FormatHelper;
use App\Service\BoxStateService;
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
     * @Route("/kiosk/ping", name="api_kiosk_ping")
     */
    public function ping(): Response {
        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/kiosk/config", name="api_kiosk_config")
     */
    public function config(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $client = null;
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
                    "client" => [
                        "id" => $order->getClient()->getId(),
                        "name" => FormatHelper::named($order->getClient()),
                        "address" => $order->getClient()->getAddress(),
                        "contact" => FormatHelper::user($order->getClient()->getContact()),
                        "phone" => $order->getClient()->getPhoneNumber(),
                        "token_amount" => $order->getClient()->get
                    ],
                    "lines" => $order->getLines()->map(fn(ClientOrderLine $line) => [
                        "box_type" => [
                            "id" => $line->getBoxType()->getId(),
                            "name" => $line->getBoxType()->getName(),
                        ],
                        "quantity" => $line->getQuantity(),
                    ])
                ]),
                "order" => $round->getOrder(),
            ])
            ->sort(fn(array $a, array $b) => $a["expected_date"] <=> $b["expected_date"])
            ->toArray();

        $result = [];
        foreach ($serialized as $round) {
            if($round["expected_date"] < $now) {
                $result[$now->format("Y-m-d")][] = $round;
            } else {
                $result[$round["expected_date"]->format("Y-m-d")][] = $round;
            }
        }

        return $this->json($result);
    }

    /**
     * @Route("/mobile/preparations", name="api_mobile_preparations")
     */
    public function preparations(EntityManagerInterface $manager, Request $request): Response {
        $depository = $manager->getRepository(Depository::class)->find($request->query->get('depository'));
        return $this->json($manager->getRepository(Preparation::class)->getByDepository($depository));
    }

    /**
     * @Route("/mobile/locations", name="api_mobile_locations")
     */
    public function locations(EntityManagerInterface $manager): Response {
        return $this->json($manager->getRepository(Location::class)->getAll());
    }

    /**
     * @Route("/mobile/qualities", name="api_mobile_qualities")
     */
    public function qualities(EntityManagerInterface $manager): Response {
        return $this->json($manager->getRepository(Quality::class)->getAll());
    }

    /**
     * @Route("/mobile/crates", name="api_mobile_crates")
     */
    public function crates(EntityManagerInterface $manager, Request $request): Response {
        $depository = $manager->getRepository(Depository::class)->find($request->query->get('depository'));
        return $this->json($manager->getRepository(Box::class)->getByDepository($depository));
    }

    /**
     * @Route("/mobile/box", name="api_mobile_box")
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

}
