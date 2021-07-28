<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\BoxRecord;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Service\BoxStateService;
use WiiCommon\Helper\Stream;
use App\Repository\BoxRepository;
use App\Service\BoxRecordService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/tracabilite/box")
 */
class BoxController extends AbstractController {

    /**
     * @Route("/liste", name="boxes_list", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("tracking/box/index.html.twig", [
            "new_box" => new Box(),
            "initial_boxes" => $this->api($request, $manager)->getContent(),
            "boxes_order" => BoxRepository::DEFAULT_DATATABLE_ORDER
        ]);
    }

    /**
     * @Route("/api", name="boxes_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $boxes = $manager->getRepository(Box::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? [], $this->getUser());

        $data = [];
        /** @var Box $box */
        foreach ($boxes["data"] as $box) {
            $data[] = [
                "id" => $box->getId(),
                "number" => $box->getNumber(),
                "creationDate" => FormatHelper::datetime($box->getCreationDate()),
                "isBox" => $box->isBox() ? 'Oui' : 'Non',
                "location" => FormatHelper::named($box->getLocation()),
                "state" => BoxStateService::BOX_STATES[$box->getState()] ?? "-",
                "quality" => FormatHelper::named($box->getQuality()),
                "owner" => FormatHelper::named($box->getOwner()),
                "type" => FormatHelper::named($box->getType()),
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
    public function new(Request $request,
                        BoxRecordService $boxRecordService,
                        EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();

        $location = isset($content->location) ? $manager->getRepository(Location::class)->find($content->location) : null;
        $owner = isset($content->owner) ? $manager->getRepository(Client::class)->find($content->owner) : null;
        $quality = isset($content->quality) ? $manager->getRepository(Quality::class)->find($content->quality) : null;
        $type = isset($content->type) ? $manager->getRepository(BoxType::class)->find($content->type) : null;
        $existing = $manager->getRepository(Box::class)->findOneBy(["number" => $content->number]);

        if ($existing) {
            $form->addError("number", "Ce numéro de Box existe déjà");
        } else if (strlen($content->number) > 50) {
            $form->addError("number", "Le numéro de Box ne peut excéder 50 caractères");
        } else if(!preg_match("/^[a-z0-9-_]{1,50}$/i", $content->number)) {
            $form->addError("number", "Le numéro de Box ne peut contenir que des lettres, chiffres, tirets et underscores");
        }

        if ($form->isValid()) {
            $box = (new Box())
                ->setNumber($content->number)
                ->setType($type)
                ->setUses(0)
                ->setCanGenerateDepositTicket(false)
                ->setLocation($location)
                ->setQuality($quality)
                ->setOwner($owner)
                ->setState($content->state ?? null)
                ->setComment($content->comment ?? null)
                ->setIsBox($content->box);
            $manager->persist($box);

            [$tracking, $record] = $boxRecordService->generateBoxRecords($box, [], $this->getUser());

            if ($tracking) {
                $tracking->setBox($box);
                $manager->persist($tracking);
            }

            if ($record) {
                $record->setBox($box);
                $manager->persist($record);
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Box créée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/voir/{box}", name="box_show", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function show(Box $box,
                         EntityManagerInterface $entityManager): Response {
        $clientOrderRepository = $entityManager->getRepository(ClientOrder::class);
        $clientOrderInProgress = $clientOrderRepository->findLastInProgressFor($box);

        return $this->render('tracking/box/show.html.twig', [
            "box" => $box,
            "clientOrderInProgress" => $clientOrderInProgress
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
    public function edit(Request $request,
                         BoxRecordService $boxRecordService,
                         EntityManagerInterface $manager,
                         Box $box): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(Box::class)->findOneBy(["number" => $content->number]);

        if ($existing !== null && $existing !== $box) {
            $form->addError("name", "Une autre Box avec ce numéro existe déjà");
        } else if (strlen($content->number) > 50) {
            $form->addError("number", "Le numéro de Box ne peut excéder 50 caractères");
        } else if(!preg_match("/^[a-z0-9-_]{1,50}$/i", $content->number)) {
            $form->addError("number", "Le numéro de Box ne peut contenir que des lettres, chiffres, tirets et underscores");
        }

        if ($form->isValid()) {
            $oldLocation = $box->getLocation();
            $oldState = $box->getState();
            $oldComment = $box->getComment();

            $location = isset($content->location) ? $manager->getRepository(Location::class)->find($content->location) : null;
            $owner = isset($content->owner) ? $manager->getRepository(Client::class)->find($content->owner) : null;
            $quality = isset($content->quality) ? $manager->getRepository(Quality::class)->find($content->quality) : null;
            $type = isset($content->type) ? $manager->getRepository(BoxType::class)->find($content->type) : null;

            $box->setNumber($content->number)
                ->setType($type)
                ->setLocation($location)
                ->setQuality($quality)
                ->setOwner($owner)
                ->setState($content->state ?? null)
                ->setComment($content->comment ?? null)
                ->setIsBox($content->box);

            [$tracking, $record] = $boxRecordService->generateBoxRecords(
                $box,
                [
                    'location' => $oldLocation,
                    'state' => $oldState,
                    'comment' => $oldComment,
                ],
                $this->getUser()
            );

            if ($tracking) {
                $tracking->setBox($box);
                $manager->persist($tracking);
            }

            if ($record) {
                $record->setBox($box);
                $manager->persist($record);
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Box modifiée avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{box}", name="box_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function deleteTemplate(Box $box): Response {
        return $this->json([
            "submit" => $this->generateUrl("box_delete", ["box" => $box->getId()]),
            "template" => $this->renderView("tracking/box/modal/delete.html.twig", [
                "box" => $box,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{box}", name="box_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function delete(EntityManagerInterface $manager, Box $box): Response {
        if ($box) {
            $manager->remove($box);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Box <strong>{$box->getNumber()}</strong> supprimée avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "La Box n'existe pas"
            ]);
        }
    }

    /**
     * @Route("/export", name="boxes_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function export(EntityManagerInterface $manager,
                           ExportService $exportService): Response {
        $boxes = $manager->getRepository(Box::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $boxes) {
            foreach ($boxes as $box) {
                $box["state"] = BoxStateService::BOX_STATES[$box["state"]];
                $exportService->putLine($output, $box);
            }
        }, "export-box-$today.csv", ExportService::BOX_HEADER);
    }

    /**
     * @Route("/{box}/mouvements", name="get_box_mouvements", options={"expose": true}, methods={"GET"})
     */
    public function getTrackingMovements(Box $box,
                                         Request $request,
                                         EntityManagerInterface $manager): JsonResponse
    {
        $boxRecordRepository = $manager->getRepository(BoxRecord::class);
        $start = $request->query->getInt('start', 0);
        $search = $request->query->has('search') ? $request->query->get('search') : null;
        $length = 10;

        $boxMovementsResult = $boxRecordRepository->getBoxRecords($box, $start, $length, $search);

        dump(Stream::from($boxMovementsResult['data']));

        return $this->json([
            'success' => true,
            'isTail' => ($start + $length) >= $boxMovementsResult['totalCount'],
            'data' => Stream::from($boxMovementsResult['data'])
                ->map(fn(array $movement) => [
                    'quality' => $movement['quality'] ?? "",
                    'color' => (isset($movement['state']) && isset(BoxStateService::LINKED_COLORS[$movement['state']]))
                        ? BoxStateService::LINKED_COLORS[$movement['state']]
                        : BoxStateService::DEFAULT_COLOR,
                    'date' => isset($movement['date'])
                        ? ($movement['date']->format("d") . ' ' . FormatHelper::MONTHS[$movement['date']->format('n')] . ' ' . $movement['date']->format("Y"))
                        : '',
                    'time' => isset($movement['date']) ? $movement['date']->format('H:i') : 'Non définie',
                    'state' => (isset($movement['state']) && isset(BoxStateService::RECORD_STATES[$movement['state']]))
                        ? BoxStateService::RECORD_STATES[$movement['state']]
                        : '-',
                    'crate' => !empty($movement['crateId'])
                        ? [
                            'number' => $movement['crateNumber'],
                            'id' => $movement['crateId']
                        ]
                        : null,
                    'operator' => $movement['operator'] ?? "",
                    'location' => $movement['location'] ?? "",
                    'depository' => $movement['depository'] ?? "",
                ])
                ->toArray(),
        ]);
    }

    /**
     * @Route("/add-box", name="add_box_in_crate", options={"expose": true}, methods={"GET"})
     */
    public function addBoxInCrate(Request $request,
                                  EntityManagerInterface $entityManager,
                                  BoxRecordService $boxRecordService){

        $boxRepository = $entityManager->getRepository(Box::class);

        $crate = $boxRepository->find($request->query->get("crate"));
        $box = $boxRepository->find($request->query->get("box"));

        if($box->getCrate()){
            return $this->json([
                "success" => false,
                "template" => $this->renderView("tracking/box/box_in_crate.html.twig",["box" => $crate]),
                "message" => "Box déjà présente dans cette caisse",
            ]);
        }
        $box->setCrate($crate);

        $tracking = $boxRecordService->createBoxRecord($box, true);

        $tracking
            ->setBox($box)
            ->setUser($this->getUser())
            ->setLocation($crate->getLocation())
            ->setCrate($crate)
            ->setState(BoxStateService::STATE_RECORD_PACKING);
        $entityManager->persist($tracking);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "template" => $this->renderView("tracking/box/box_in_crate.html.twig",["box" => $crate]),
            "message" => "Box ajoutée à la caisse avec succès",
        ]);
    }

    /**
     * @Route("/supprimer-box-in-crate/template/{box}", name="box_delete_in_crate_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function deleteBoxInCrateTemplate(Box $box): Response {
        return $this->json([
            "submit" => $this->generateUrl("box_delete_in_crate", ["box" => $box->getId()]),
            "template" => $this->renderView("tracking/box/modal/delete_box_in_crate.html.twig", [
                "box" => $box,
            ])
        ]);
    }

    /**
     * @Route("/supprimer-box-in-crate", name="box_delete_in_crate", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function deleteBoxInCrate(Request $request,
                                     EntityManagerInterface $entityManager,
                                     BoxRecordService $boxRecordService): Response {

        /** @var Box $box */
        $box = $entityManager->getRepository(Box::class)->find($request->query->get("box"));

        $oldCrate = $box->getCrate();

        $tracking = $boxRecordService->createBoxRecord($box, true);

        $tracking
            ->setBox($box)
            ->setUser($this->getUser())
            ->setLocation($oldCrate->getLocation())
            ->setCrate($oldCrate)
            ->setState(BoxStateService::STATE_RECORD_UNPACKING);

        $box->setCrate(null);
        $entityManager->persist($tracking);
        $entityManager->flush();

        return $this->json([
            "success" => true,
            "template" => $this->renderView("tracking/box/box_in_crate.html.twig", ["box" => $oldCrate]),
            "message" => "Box <strong>{$box->getNumber()}</strong> supprimée avec succès"
        ]);
    }

    /**
     * @Route("/box-in-crate-api", name="box_in_crate_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_BOXES)
     */
    public function boxInCrateApi(Request $request, EntityManagerInterface $manager) {
        $id = $request->query->get('id');

        $crate = $manager->getRepository(Box::class)->find($id);

        return $this->json([
            "success" => true,
            "template" => $this->renderView("tracking/box/box_in_crate.html.twig", ["box" => $crate]),
        ]);
    }


    /**
     * @Route("/crate-average-volume", name="get_crate_average_volume", options={"expose": true})
     */
    public function getCrateAverageVolume(EntityManagerInterface $entityManager): JsonResponse {
        $boxRepository = $entityManager->getRepository(Box::class);
        return $this->json([
            'average' => $boxRepository->getCrateAverageVolume()
        ]);
    }
}
