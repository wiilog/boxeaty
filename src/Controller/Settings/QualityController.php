<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Quality;
use App\Entity\Role;
use App\Helper\Form;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/qualites")
 */
class QualityController extends AbstractController {

    /**
     * @Route("/liste", name="qualities_list")
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function list(): Response {
        return $this->render("settings/quality/index.html.twig", [
            "new_quality" => new Quality(),
        ]);
    }

    /**
     * @Route("/api", name="qualities_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $qualityRepository = $manager->getRepository(Quality::class);
        $qualities = $qualityRepository->findForDatatable(json_decode($request->getContent(), true));
        $deletable = $qualityRepository->getDeletable($qualities["data"]);

        $data = [];
        foreach ($qualities["data"] as $quality) {
            $data[] = [
                "id" => $quality->getId(),
                "name" => $quality->getName(),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => $deletable[$quality->getId()],
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
     * @Route("/nouveau", name="quality_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Quality::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Une qualité avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $quality = new Quality();
            $quality->setName($content->name);

            $manager->persist($quality);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Qualité créée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{quality}", name="quality_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function editTemplate(Quality $quality): Response {
        return $this->json([
            "submit" => $this->generateUrl("quality_edit", ["quality" => $quality->getId()]),
            "template" => $this->renderView("settings/quality/modal/edit.html.twig", [
                "quality" => $quality,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{quality}", name="quality_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Quality $quality): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Quality::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $quality) {
            $form->addError("name", "Une autre qualité avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $quality->setName($content->name);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Qualité modifiée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="quality_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_QUALITIES)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $quality = $manager->getRepository(Quality::class)->find($content->id);

        //TODO: check if quality is used by users
        if ($quality) {
            $manager->remove($quality);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Quality <strong>{$quality->getName()}</strong> supprimée avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "La qualité n'existe pas"
            ]);
        }
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
