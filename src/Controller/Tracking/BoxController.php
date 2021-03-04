<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\TrackingMovement;
use App\Helper\Form;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/box")
 */
class BoxController extends AbstractController {

    /**
     * @Route("/liste", name="boxes_list")
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function list(): Response {

        return $this->render("tracking/box/index.html.twig", [
            "new_box" => new Box(),
        ]);
    }

    /**
     * @Route("/api", name="boxes_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $boxes = $manager->getRepository(Box::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($boxes["data"] as $box) {
            $data[] = [
                "id" => $box->getId(),
                "number" => $box->getNumber(),
                "location" => $box->getLocation() ? $box->getLocation()->getName() : '',
                "state" => Box::NAMES[$box->getState()] ?? "",
                "quality" => $box->getQuality() ? $box->getQuality()->getName() : '',
                "owner" => $box->getOwner() ? $box->getOwner()->getName() : '',
                "type" => $box->getType() ? $box->getType()->getName() : '',
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $boxes["total"],
            "recordsFiltered" => $boxes["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="box_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSIT_TICKETS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $location = $manager->getRepository(Location::class)->find($content->location);
        $owner = $manager->getRepository(Client::class)->find($content->owner);
        $quality = $manager->getRepository(Quality::class)->find($content->quality);
        $type = $manager->getRepository(BoxType::class)->find($content->type);
        $existing = $manager->getRepository(Box::class)->findOneBy(["number" => $content->number]);
        if ($existing) {
            $form->addError("number", "Ce numéro de Box existe déjà");
        } else if (strlen($content->number) > 50) {
            $form->addError("number", "Le numéro de Box ne peut excéder 50 caractères");
        }

        if ($form->isValid()) {
            $box = new Box();

            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setBox($box)
                ->setLocation($location)
                ->setClient($owner)
                ->setQuality($quality)
                ->setState($content->state)
                ->setComment($content->comment ?? null);

            $box->setNumber($content->number)
                ->setType($type)
                ->setCanGenerateDepositTicket(false)
                ->fromTrackingMovement($movement);

            $manager->persist($box);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Box créée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/voir/{box}", name="box_show", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function show(EntityManagerInterface $manager, Box $box): Response {
        $box = $manager->getRepository(Box::class)->find($box);

        return $this->render('tracking/box/show.html.twig', [
            "box" => $box,
        ]);
    }

    /**
     * @Route("/modifier/template/{box}", name="box_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function editTemplate(Box $box): Response {
        return $this->json([
            "submit" => $this->generateUrl("box_edit", ["box" => $box->getId()]),
            "template" => $this->renderView("tracking/box/modal/edit.html.twig", [
                "box" => $box,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{box}", name="box_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Box $box): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $location = $manager->getRepository(Location::class)->find($content->location);
        $owner = $manager->getRepository(Client::class)->find($content->owner);
        $quality = $manager->getRepository(Quality::class)->find($content->quality);
        $type = $manager->getRepository(BoxType::class)->find($content->type);
        $existing = $manager->getRepository(Box::class)->findOneBy(["number" => $content->number]);
        if ($existing !== null && $existing !== $box) {
            $form->addError("name", "Une autre Box avec ce numéro existe déjà");
        }

        if ($form->isValid()) {
            $movement = (new TrackingMovement())
                ->setDate(new DateTime())
                ->setBox($box)
                ->setLocation($location)
                ->setClient($owner)
                ->setQuality($quality)
                ->setState($content->state)
                ->setComment($content->comment ?? null);

            $box->setNumber($content->number)
                ->setType($type)
                ->fromTrackingMovement($movement);

            $manager->persist($movement);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Box modifiée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="box_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = (object)$request->request->all();
        $box = $manager->getRepository(Box::class)->find($content->id);

        if ($box) {
            $manager->remove($box);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Box <strong>{$box->getNumber()}</strong> supprimée avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "La Box n'existe pas"
            ]);
        }
    }

    /**
     * @Route("/export", name="boxes_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $boxes = $manager->getRepository(Box::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $boxes) {
            foreach ($boxes as $box) {
                $box["state"] = Box::NAMES[$box["state"]];
                $exportService->putLine($output, $box);
            }
        }, "export-box-$today.csv", ExportService::BOX_HEADER);
    }

}
