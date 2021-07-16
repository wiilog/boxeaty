<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\Client;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\User;
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

            $box->setState(Box::UNAVAILABLE)
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
            "state" => Box::CONSUMER,
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
            "state" => Box::CONSUMER,
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
                ->setState(Box::UNAVAILABLE)
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
        if($user && $hasher->isPasswordValid($user, $content->password)) {
            return $this->json([
                "success" => true,
            ]);
        }

        return $this->json([
            "success" => false,
            "message" => "Identifiants invalides"
        ]);
    }

}
