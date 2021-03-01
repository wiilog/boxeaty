<?php

namespace App\Controller;

use App\Entity\Box;
use App\Entity\GlobalSetting;
use App\Entity\Location;
use App\Entity\TrackingMovement;
use App\Helper\Stream;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
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
        $kiosk = $manager->getRepository(Location::class)->find($content->kiosk);

        //TODO: actually empty it

        return $this->json([
            "success" => true,
        ]);
    }

    /**
     * @Route("/check-code", name="api_check_code")
     */
    public function checkCode(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());

        try {
            $page = $manager->getRepository(GlobalSetting::class)->getCorrespondingCode($content->code);

            return $this->json([
                "success" => true,
                "page" => $page["name"],
            ]);
        } catch (NoResultException $ignored) {
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
            $box->setState(Box::UNAVAILABLE)
                ->setLocation($kiosk);

            $movement = (new TrackingMovement())
                ->setBox($box)
                ->setState(Box::UNAVAILABLE)
                ->setQuality($box->getQuality())
                ->setClient($box->getOwner())
                ->setDate(new DateTime())
                ->setUser(null);

            $manager->persist($movement);
            $manager->flush();

            return $this->json([
                "success" => true,
                "box" => [
                    "number" => $box->getNumber(),
                ],
            ]);
        } else {
            return $this->json([
                "success" => false,
            ]);
        }
    }

}
