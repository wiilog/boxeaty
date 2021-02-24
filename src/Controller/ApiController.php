<?php

namespace App\Controller;

use App\Entity\GlobalSetting;
use App\Entity\Kiosk;
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
        $kiosks = $manager->getRepository(Kiosk::class)->getAll();

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
        $kiosk = $manager->getRepository(Kiosk::class)->find($content->kiosk);

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
        } catch(NoResultException $ignored) {
            return $this->json([
                "success" => false,
            ]);
        }
    }

}
