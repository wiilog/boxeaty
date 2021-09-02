<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Controller\AbstractController;
use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\Client;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Repository\BoxRecordRepository;
use App\Service\BoxRecordService;
use App\Service\BoxStateService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/mouvements")
 */
class TrackingMovementController extends AbstractController {

    /**
     * @Route("/liste", name="tracking_movements_list")
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        $qualities = $manager->getRepository(Quality::class)->findAll();

        return $this->render("tracking/movement/index.html.twig", [
            "new_movement" => new BoxRecord(),
            "initial_movements" => $this->api($request, $manager)->getContent(),
            "movements_order" => BoxRecordRepository::DEFAULT_DATATABLE_ORDER,
            "qualities" => $qualities,
            "states" => BoxStateService::RECORD_STATES
        ]);
    }

    /**
     * @Route("/api", name="tracking_movements_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $movements = $manager->getRepository(BoxRecord::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $actions = $this->renderView("datatable_actions.html.twig", [
            "editable" => true,
            "deletable" => true,
        ]);

        $data = [];
        /** @var BoxRecord $movement */
        foreach ($movements["data"] as $movement) {
            $data[] = [
                "id" => $movement->getId(),
                "date" => FormatHelper::datetime($movement->getDate()),
                "location" => FormatHelper::named($movement->getLocation()),
                "box" => $movement->getBox()->getNumber(),
                "quality" => FormatHelper::named($movement->getQuality()),
                "state" => BoxStateService::RECORD_STATES[$movement->getState()] ?? "-",
                "client" => FormatHelper::named($movement->getClient()),
                "user" => FormatHelper::user($movement->getUser()),
                "actions" => $actions,
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $movements["total"],
            "recordsFiltered" => $movements["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="tracking_movement_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     * @throws Exception
     */
    public function new(Request $request,
                        BoxRecordService $boxRecordService,
                        EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object) $request->request->all();
        $trackingMovementRepository = $manager->getRepository(BoxRecord::class);

        /** @var Box $box */
        $box = $manager->getRepository(Box::class)->find($content->box);
        if (!$box) {
            $form->addError("box", "Cette Box n'existe pas ou a changé de numéro");
        }

        if ($form->isValid()) {
            $oldState = $box->getState();
            $oldComment = $box->getComment();

            $quality = isset($content->quality) ? $manager->getRepository(Quality::class)->find($content->quality) : null;
            $client = isset($content->client) ? $manager->getRepository(Client::class)->find($content->client) : null;
            $location = isset($content->location) ? $manager->getRepository(Location::class)->find($content->location) : null;

            $movement = (new BoxRecord())
                ->setTrackingMovement(true)
                ->setDate(new DateTime($content->date))
                ->setBox($box)
                ->setQuality($quality)
                ->setState($content->state ?? null)
                ->setClient($client)
                ->setLocation($location)
                ->setUser($this->getUser())
                ->setComment($content->comment ?? null);

            $manager->persist($movement);
            $manager->flush();

            $newerMovement = $trackingMovementRepository->findNewerTrackingMovement($movement);
            if (!$newerMovement) {
                $previous = clone $box;
                $box->fromRecord($movement);

                [$tracking] = $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
                $boxRecordService->remove($tracking);

                $manager->flush();
            }


            return $this->json([
                "success" => true,
                "message" => "Mouvement de traçabilité créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{movement}", name="tracking_movement_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function editTemplate(EntityManagerInterface $manager, BoxRecord $movement): Response {
        $qualities = $manager->getRepository(Quality::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("tracking_movement_edit", ["movement" => $movement->getId()]),
            "template" => $this->renderView("tracking/movement/modal/edit.html.twig", [
                "movement" => $movement,
                "qualities" => $qualities,
                "states" => BoxStateService::RECORD_STATES,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{movement}", name="tracking_movement_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function edit(Request $request,
                         BoxRecordService $boxRecordService,
                         EntityManagerInterface $manager,
                         BoxRecord $movement): Response {
        $form = Form::create();

        $content = (object) $request->request->all();
        $box = $manager->getRepository(Box::class)->find($content->box);
        if (!$box) {
            $form->addError("box", "Cette Box n'existe pas ou a changé de numéro");
        }

        $trackingMovementRepository = $manager->getRepository(BoxRecord::class);
        $quality = isset($content->quality) ? $manager->getRepository(Quality::class)->find($content->quality) : null;
        $client = isset($content->client) ? $manager->getRepository(Client::class)->find($content->client) : null;
        $location = isset($content->location) ? $manager->getRepository(Location::class)->find($content->location) : null;

        if ($form->isValid()) {
            $movement->setDate(new DateTime($content->date))
                ->setQuality($quality)
                ->setState($content->state ?? null)
                ->setClient($client)
                ->setLocation($location)
                ->setComment($content->comment ?? null);

            $manager->flush();

            $newerMovement = $trackingMovementRepository->findNewerTrackingMovement($movement);
            if (!$newerMovement) {
                $previous = clone $box;
                $box->fromRecord($movement);

                [$tracking] = $boxRecordService->generateBoxRecords($box, $previous, $this->getUser());
                $boxRecordService->remove($tracking);

                $manager->flush();
            }

            return $this->json([
                "success" => true,
                "message" => "Mouvement de traçabilité modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="tracking_movement_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = (object) $request->request->all();
        $movement = $manager->getRepository(BoxRecord::class)->find($content->id);

        if ($movement) {
            $manager->remove($movement);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Mouvement de traçabilité supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "message" => "Le mouvement de traçabilité a déjà été supprimé",
            ]);
        }
    }

    /**
     * @Route("/export", name="tracking_movement_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $movements = $manager->getRepository(BoxRecord::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $movements) {
            foreach ($movements as $movement) {
                $movement["state"] = BoxStateService::RECORD_STATES[$movement["state"]] ?? "Inconnu";
                $exportService->putLine($output, $movement);
            }
        }, "export-tracabilite-$today.csv", ExportService::MOVEMENT_HEADER);
    }

}
