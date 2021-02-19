<?php

namespace App\Controller\Tracking;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Quality;
use App\Entity\Role;
use App\Entity\State;
use App\Entity\TrackingMovement;
use App\Entity\User;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use App\Helper\Form;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function list(EntityManagerInterface $manager): Response {
        $qualities = $manager->getRepository(Quality::class)->findAll();
        $states = $manager->getRepository(State::class)->findAll();
        $groups = $manager->getRepository(Group::class)->findAll();
        $users = $manager->getRepository(User::class)->findAll();

        return $this->render("tracking/movement/index.html.twig", [
            "new_movement" => new TrackingMovement(),
            "qualities" => $qualities,
            "states" => $states,
            "groups" => $groups,
            "users" => $users
        ]);
    }

    /**
     * @Route("/api", name="tracking_movements_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $movements = $manager->getRepository(TrackingMovement::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $actions = $this->renderView("tracking/movement/datatable_actions.html.twig");

        $data = [];
        foreach ($movements["data"] as $movement) {
            $data[] = [
                "id" => $movement->getId(),
                "date" => $movement->getDate()->format("d/m/Y H:i"),
                "box" => $movement->getBox()->getNumber(),
                "quality" => $movement->getQuality()->getName(),
                "state" => $movement->getState()->getName(),
                "client" => $movement->getClient()->getName(),
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
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $box = $manager->getRepository(Box::class)->find($content->box);
        if (!$box) {
            $form->addError("box", "Cette Box n'existe pas ou a changé de numéro");
        }

        $quality = $manager->getRepository(Quality::class)->find($content->quality);
        if (!$quality) {
            $form->addError("quality", "Cette qualité n'existe pas ou plus");
        }

        $state = $manager->getRepository(State::class)->find($content->state);
        if (!$state) {
            $form->addError("state", "Cet état n'existe pas");
        }

        $client = $manager->getRepository(Client::class)->find($content->client);
        if (!$client) {
            $form->addError("client", "Ce client n'existe pas ou plus");
        }

        if ($form->isValid()) {
            $movement = new TrackingMovement();
            $movement->setDate(new DateTime($content->date))
                ->setBox($box)
                ->setQuality($quality)
                ->setState($state)
                ->setClient($client)
                ->setComment(strip_tags($content->comment) ?? null);

            $manager->persist($movement);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Mouvement de traçabilité créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{movement}", name="tracking_movement_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function editTemplate(EntityManagerInterface $manager, TrackingMovement $movement): Response {
        $qualities = $manager->getRepository(Quality::class)->findAll();
        $states = $manager->getRepository(State::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("tracking_movement_edit", ["movement" => $movement->getId()]),
            "template" => $this->renderView("tracking/movement/modal/edit.html.twig", [
                "movement" => $movement,
                "qualities" => $qualities,
                "states" => $states,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{movement}", name="tracking_movement_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, TrackingMovement $movement): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $box = $manager->getRepository(Box::class)->find($content->box);
        if (!$box) {
            $form->addError("box", "Cette Box n'existe pas ou a changé de numéro");
        }

        $quality = $manager->getRepository(Quality::class)->find($content->quality);
        if (!$quality) {
            $form->addError("quality", "Cette qualité n'existe plus");
        }

        $state = $manager->getRepository(State::class)->find($content->state);
        if (!$state) {
            $form->addError("state", "Cet état n'existe pas");
        }

        $client = $manager->getRepository(Client::class)->find($content->client);
        if (!$client) {
            $form->addError("client", "Ce client n'existe pas ou plus");
        }

        $user = $manager->getRepository(User::class)->find($content->user);

        if ($form->isValid()) {
            $movement->setDate(new DateTime($content->date))
                ->setBox($box)
                ->setQuality($quality)
                ->setState($state)
                ->setClient($client)
                ->setUser($user)
                ->setComment($content->comment ?? null);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Mouvement de traçabilité modifié avec succès",
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
        $content = json_decode($request->getContent());
        $movement = $manager->getRepository(User::class)->find($content->id);

        if ($movement) {
            $manager->remove($movement);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Mouvement de traçabilité supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Le mouvement de traçabilité a déjà été supprimé",
            ]);
        }
    }

    /**
     * @Route("/export", name="tracking_movement_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_MOVEMENTS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $movements = $manager->getRepository(TrackingMovement::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        $header = array_merge([
            "Date",
            "Numéro de box",
            "Qualité",
            "Etat",
            "Client",
            "Commentaire",
        ]);

        return $exportService->export(function($output) use ($exportService, $movements) {
            foreach ($movements as $movement) {
                $exportService->putLine($output, $movement);
            }
        }, "export-tracabilite-$today.csv", $header);
    }

}