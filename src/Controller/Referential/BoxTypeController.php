<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\BoxType;
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
 * @Route("/referentiel/type-box")
 */
class BoxTypeController extends AbstractController {

    /**
     * @Route("/liste", name="box_types_list")
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function list(): Response {
        return $this->render("referential/box_type/index.html.twig", [
            "new_box_type" => new BoxType(),
        ]);
    }

    /**
     * @Route("/api", name="box_types_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $boxTypes = $manager->getRepository(BoxType::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($boxTypes["data"] as $boxType) {
            $data[] = [
                "id" => $boxType->getId(),
                "name" => $boxType->getName(),
                "price" => $boxType->getPrice(),
                "active" => $boxType->isActive() ? "Oui" : "Non",
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

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(BoxType::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Ce type de box existe déjà");
        }

        if($form->isValid()) {
            $boxType = new BoxType();
            $boxType->setName($content->name)
                ->setPrice($content->price)
                ->setActive($content->active);

            $manager->persist($boxType);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Type de box créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{boxType}", name="box_type_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function editTemplate(BoxType $boxType): Response {
        return $this->json([
            "submit" => $this->generateUrl("box_type_edit", ["boxType" => $boxType->getId()]),
            "template" => $this->renderView("referential/box_type/modal/edit.html.twig", [
                "box_type" => $boxType,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{boxType}", name="box_type_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOX_TYPES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, BoxType $boxType): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(BoxType::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $boxType) {
            $form->addError("name", "Un autre type de box avec ce nom existe déjà");
        }

        if($form->isValid()) {
            $boxType->setName($content->name)
                ->setPrice($content->price)
                ->setActive($content->active);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Type de box modifié avec succès",
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

        $header = array_merge([
            "Type de box",
            "Prix",
            "Actif",
        ]);

        return $exportService->export(function($output) use ($exportService, $boxTypes) {
            foreach ($boxTypes as $boxType) {
                $exportService->putLine($output, $boxType);
            }
        }, "export-box-type-$today.csv", $header);
    }

}
