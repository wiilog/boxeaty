<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\BoxType;
use App\Entity\Client;
use App\Entity\Location;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\BoxRecord;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Helper\Stream;
use App\Repository\BoxRepository;
use App\Service\BoxRecordService;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use DoctrineExtensions\Query\Mysql\Date;
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
     * @Route("/liste", name="boxes_list")
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
        foreach ($boxes["data"] as $box) {
            $data[] = [
                "id" => $box->getId(),
                "number" => $box->getNumber(),
                "creationDate" => FormatHelper::datetime($box->getCreationDate()),
                "location" => FormatHelper::named($box->getLocation()),
                "state" => Box::NAMES[$box->getState()] ?? "-",
                "quality" => FormatHelper::named($box->getQuality()),
                "owner" => FormatHelper::named($box->getOwner()),
                "type" => FormatHelper::named($box->getType()),
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
                ->setComment($content->comment ?? null);
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
    public function show(Box $box): Response {
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
                ->setComment($content->comment ?? null);

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
                $box["state"] = Box::NAMES[$box["state"]];
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

        return $this->json([
            'success' => true,
            'isTail' => ($start + $length) >= $boxMovementsResult['totalCount'],
            'data' => Stream::from($boxMovementsResult['data'])
                ->map(fn(array $movement) => [
                    'comment' => str_replace("Powered by Froala Editor", "", $movement['comment']),
                    'color' => (isset($movement['state']) && isset(Box::LINKED_COLORS[$movement['state']]))
                        ? Box::LINKED_COLORS[$movement['state']]
                        : Box::DEFAULT_COLOR,
                    'date' => isset($movement['date']) ? $movement['date']->format('d/m/Y à H:i:s') : 'Non définie',
                    'state' => (isset($movement['state']) && isset(Box::NAMES[$movement['state']]))
                        ? Box::NAMES[$movement['state']]
                        : '-',
                ])
                ->toArray(),
        ]);
    }
}
