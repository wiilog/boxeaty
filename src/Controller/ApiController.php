<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\TrackingMovement;
use App\Entity\User;
use App\Helper\Stream;
use App\Helper\StringHelper;
use App\Service\Mailer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class ApiController extends AbstractController {

    private const BOX_CAPACITY = 50;

    /**
     * @Route("/ping", name="api_ping")
     */
    public function ping(): Response {
        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/config", name="api_config")
     */
    public function config(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $phrase = $manager->getRepository(GlobalSetting::class)->getValue(GlobalSetting::TABLET_PHRASE);

        $client = null;
        if (isset($content->id)) {
            $kiosk = $manager->getRepository(Location::class)->find($content->id);
            if ($kiosk) {
                $client = $kiosk->getClient();
                if ($client && !$client->isMultiSite() && $client->getLinkedMultiSite()) {
                    $client = $client->getLinkedMultiSite();
                }
            }
        }

        return $this->json([
            "phrase" => $phrase ?: ($client ? "{$client->getName()} s'engage avec BoxEaty<br>dans la réduction des déchets" : ""),
        ]);
    }

    /**
     * @Route("/kiosks", name="api_kiosks")
     */
    public function kiosks(EntityManagerInterface $manager): Response {
        $kiosks = Stream::from($manager->getRepository(Location::class)->findBy(["kiosk" => true]))
            ->map(fn(Location $kiosk) => [
                "id" => $kiosk->getId(),
                "name" => $kiosk->getName(),
                "capacity" => self::BOX_CAPACITY,
                "client" => null,
                "boxes" => Stream::from($kiosk->getBoxes())
                    ->map(fn(Box $box) => [
                        "id" => $box->getId(),
                        "number" => $box->getNumber(),
                    ])
                    ->toArray(),
            ])
            ->toArray();

        return $this->json([
            "success" => true,
            "kiosks" => $kiosks,
        ]);
    }

    /**
     * @Route("/check-code", name="api_check_code")
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
     * @Route("/kiosks/reload", name="api_kiosks_reload")
     */
    public function kiosk(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);

        if ($kiosk) {
            return $this->json([
                "success" => true,
                "kiosk" => [
                    "id" => $kiosk->getId(),
                    "name" => $kiosk->getName(),
                    "capacity" => self::BOX_CAPACITY,
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
                "msg" => "Borne inexistante",
            ]);
        }
    }

    /**
     * @Route("/kiosks/empty", name="api_empty_kiosk", options={"expose": true})
     */
    public function emptyKiosk(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk ?? $request->request->get("id"));

        foreach ($kiosk->getBoxes() as $box) {
            $user = $this->getUser();

            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setBox($box)
                ->setClient($box->getOwner())
                ->setQuality($box->getQuality())
                ->setState(Box::UNAVAILABLE)
                ->setLocation($kiosk->getDeporte())
                ->setComment($content->comment ?? null)
                ->setUser($user instanceof User ? $user : null);

            $box->fromTrackingMovement($movement);

            $manager->persist($movement);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/box/retrieve", name="api_retrieve_box")
     */
    public function retrieveBox(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => Box::CONSUMER,
        ]);

        if ($box
            && $box->getType()
            && $box->getOwner()) {
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
                "msg" => "La Box n'existe pas ou n'est pas sale",
            ]);
        }
    }

    /**
     * @Route("/box/drop", name="api_drop_box")
     */
    public function dropBox(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);
        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => Box::CONSUMER,
        ]);

        if ($box
            && $box->getType()
            && $box->getOwner()) {
            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setBox($box)
                ->setLocation($kiosk)
                ->setClient($box->getOwner())
                ->setQuality($box->getQuality())
                ->setState(Box::UNAVAILABLE)
                ->setComment($content->comment ?? null)
                ->setUser(null);

            $kiosk->setDeposits($kiosk->getDeposits() + 1);;

            $box->setCanGenerateDepositTicket(true)
                ->setUses($box->getUses() + 1)
                ->fromTrackingMovement($movement);

            $manager->persist($movement);
            $manager->flush();

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
                "msg" => "La Box n'existe pas, veuillez contacter un responsable d'établissement.",
            ]);
        }
    }

    /**
     * @Route("/deposit-ticket/statistics", name="api_deposit_ticket_statistics")
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
     * @Route("/deposit-ticket/mail", name="api_deposit_ticket_mail")
     */
    public function mailDepositTicket(Request $request, EntityManagerInterface $manager, Mailer $mailer): Response {
        $content = json_decode($request->getContent());

        $box = $manager->getRepository(Box::class)->find($content->box);
        $validity = $manager->getRepository(Location::class)->find($content->kiosk)
            ->getClient()
            ->getDepositTicketValidity();

        if (!$box->getCanGenerateDepositTicket()) {
            return $this->json([
                "success" => false,
                "msg" => "Cette box n'a pas été déposée",
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

        $mailer->send(
            $content->email,
            "BoxEaty - Ticket-consigne",
            $this->renderView("emails/deposit_ticket.html.twig", [
                "ticket" => $depositTicket,
            ])
        );

        $manager->persist($depositTicket);
        $manager->flush();

        return $this->json([
            "success" => true,
        ]);
    }

}
