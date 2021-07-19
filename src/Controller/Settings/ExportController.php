<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Quality;
use App\Entity\Role;
use App\Helper\Form;
use App\Repository\QualityRepository;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/export")
 */
class ExportController extends AbstractController {

    /**
     * @Route("/index", name="exports_index")
     * @HasPermission(Role::MANAGE_EXPORTS)
     */
    public function index(): Response {
        return $this->render("settings/export/index.html.twig");
    }

    /**
     * @Route("/api", name="qualities_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $qualityRepository = $manager->getRepository(Quality::class);
        $qualities = $qualityRepository->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];
        foreach ($qualities["data"] as $quality) {
            $data[] = [
                "id" => $quality->getId(),
                "name" => $quality->getName(),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $qualities["total"],
            "recordsFiltered" => $qualities["filtered"],
        ]);
    }

    /**
     * @Route("/export", name="qualities_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $qualities = $manager->getRepository(Quality::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $qualities) {
            foreach ($qualities as $quality) {
                $exportService->putLine($output, $quality);
            }
        }, "export-qualites-$today.csv", ExportService::QUALITY_HEADER);
    }

}
