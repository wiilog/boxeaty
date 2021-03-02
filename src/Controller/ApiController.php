<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\DepositTicket;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\TrackingMovement;
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

    /**
     * @Route("/ping", name="api_ping")
     */
    public function ping(): Response {
        return $this->json([
            "success" => true,
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
                "capacity" => 50,
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
     * @Route("/kiosks/empty", name="api_empty_kiosk")
     */
    public function emptyKiosk(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $deliverer = $manager->getRepository(Location::class)->findDeliverer();
        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);

        foreach($kiosk->getBoxes() as $box) {
            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setBox($box)
                ->setState(Box::UNAVAILABLE)
                ->setLocation($deliverer)
                ->setUser(null);

            $box->fromTrackingMovement($movement);

            $manager->persist($movement);
        }

        $manager->flush();

        return $this->json([
            "success" => true,
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
     * @Route("/box/retrieve", name="api_retrieve_box")
     */
    public function retrieveBox(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $box = $manager->getRepository(Box::class)->findOneBy([
            "number" => $content->number,
            "state" => Box::CONSUMER,
        ]);

        if ($box) {
            return $this->json([
                "success" => true,
                "box" => [
                    "number" => $box->getNumber(),
                ],
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "La Box n'existe pas",
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

        if ($box) {
            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setLocation($kiosk)
                ->setClient($box->getOwner())
                ->setQuality($box->getQuality())
                ->setState(Box::UNAVAILABLE)
                ->setComment($content->comment ?? null)
                ->setUser(null);

            $box->setCanGenerateDepositTicket(true)
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
                "msg" => "La Box n'existe pas",
            ]);
        }
    }

    /**
     * @Route("/deposit-ticket/statistics", name="api_deposit_ticket_statistics")
     */
    public function depositTicketStatistics(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        $locationRepository = $manager->getRepository(Location::class);
        $kiosk = $locationRepository->find($content->kiosk);
        $totalDeposits = $locationRepository->getTotalDeposits();

        $lessWaste = $kiosk->getDeposits() * 32;
        if($lessWaste > 1000) {
            $prefix = "kg";
            $lessWaste = round($lessWaste / 1000);
        } else if($lessWaste > 1000000) {
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
        $validity = $box->getLocation()->getClient()->getDepositTicketValidity();

        if(!$box->getCanGenerateDepositTicket()) {
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
            "BoxEaty - Ticket consigne",
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
