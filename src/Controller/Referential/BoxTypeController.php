<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\BoxType;
use App\Entity\GlobalSetting;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\BoxTypeRepository;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/referentiel/type-box")
 */
class BoxTypeController extends AbstractController {

    /**
     * @Route("/liste", name="box_types_list")
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $settingsRepository = $manager->getRepository(GlobalSetting::class);
        $capacities = explode(",", $settingsRepository->getValue(GlobalSetting::BOX_CAPACITIES));
        $shapes = explode(",", $settingsRepository->getValue(GlobalSetting::BOX_SHAPES));

        return $this->render("referential/box_type/index.html.twig", [
            "new_box_type" => new BoxType(),
            "initial_box_types" => $this->api($request, $manager)->getContent(),
            "box_types_order" => BoxTypeRepository::DEFAULT_DATATABLE_ORDER,
            "capacities" => $capacities ?: [],
            "shapes" => $shapes ?: [],
        ]);
    }

    /**
     * @Route("/api", name="box_types_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $boxTypes = $manager->getRepository(BoxType::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];
        foreach ($boxTypes["data"] as $boxType) {
            $data[] = [
                "id" => $boxType->getId(),
                "name" => $boxType->getName(),
                "price" => FormatHelper::price($boxType->getPrice()),
                "capacity" => $boxType->getCapacity() ?: "-",
                "shape" => $boxType->getShape() ?: "-",
                "active" => $boxType->isActive() ? "Oui" : "Non",
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $boxTypes["total"],
            "recordsFiltered" => $boxTypes["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="box_type_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(BoxType::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Ce type de Box existe déjà");
        }

        if ($content->price < 0) {
            $form->addError("price", "Le prix doit être supérieur ou égal à 0");
        }

        if ($form->isValid()) {
            $boxType = new BoxType();
            $boxType->setName($content->name)
                ->setPrice($content->price)
                ->setActive($content->active)
                ->setCapacity($content->capacity)
                ->setShape($content->shape);

            $manager->persist($boxType);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Type de Box créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{boxType}", name="box_type_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function editTemplate(EntityManagerInterface $manager, BoxType $boxType): Response {
        $settingsRepository = $manager->getRepository(GlobalSetting::class);
        $capacities = explode(",", $settingsRepository->getValue(GlobalSetting::BOX_CAPACITIES));
        $shapes = explode(",", $settingsRepository->getValue(GlobalSetting::BOX_SHAPES));

        return $this->json([
            "submit" => $this->generateUrl("box_type_edit", ["boxType" => $boxType->getId()]),
            "template" => $this->renderView("referential/box_type/modal/edit.html.twig", [
                "box_type" => $boxType,
                "capacities" => $capacities ?: [],
                "shapes" => $shapes ?: [],
            ])
        ]);
    }

    /**
     * @Route("/modifier/{boxType}", name="box_type_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, BoxType $boxType): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(BoxType::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $boxType) {
            $form->addError("name", "Un autre type de Box avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $boxType->setName($content->name)
                ->setPrice($content->price)
                ->setActive($content->active)
                ->setCapacity($content->capacity)
                ->setShape($content->shape);

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Type de Box modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/export", name="box_types_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $boxTypes = $manager->getRepository(BoxType::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $boxTypes) {
            foreach ($boxTypes as $boxType) {
                $exportService->putLine($output, $boxType);
            }
        }, "export-type-de-box-$today.csv", ExportService::BOX_TYPE_HEADER);
    }

}
