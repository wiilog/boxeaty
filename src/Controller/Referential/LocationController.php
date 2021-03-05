<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Box;
use App\Entity\Client;
use App\Entity\Location;
use App\Entity\Role;
use App\Entity\TrackingMovement;
use App\Helper\Form;
use App\Helper\FormatHelper;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/referentiel/emplacements")
 */
class LocationController extends AbstractController {

    /**
     * @Route("/liste", name="locations_list")
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function list(): Response {
        return $this->render("referential/location/index.html.twig", [
            "new_location" => new Location(),
        ]);
    }

    /**
     * @Route("/api", name="locations_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $locations = $manager->getRepository(Location::class)
            ->findForDatatable(json_decode($request->getContent(), true), $this->getUser());

        $data = [];
        foreach ($locations["data"] as $location) {
            $data[] = [
                "id" => $location->getId(),
                "type" => $location->isKiosk() ? "Borne" : "Emplacement",
                "name" => $location->getName(),
                "active" => $location->isActive() ? "Oui" : "Non",
                "client" => FormatHelper::named($location->getClient()),
                "boxes" => $location->getBoxes()->count(),
                "description" => $location->getDescription(),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                    "empty" => $location->isKiosk(),
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $locations["total"],
            "recordsFiltered" => $locations["filtered"]
        ]);
    }

    /**
     * @Route("/nouveau", name="location_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $client = isset($content->client) ? $manager->getRepository(Client::class)->find($content->client) : null;

        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Un emplacement avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $location = new Location();
            $location->setKiosk($content->type)
                ->setName($content->name)
                ->setActive($content->active)
                ->setClient($client)
                ->setDescription($content->description ?? null)
                ->setDeposits(0);

            $manager->persist($location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Emplacement créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{location}", name="location_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function editTemplate(Location $location): Response {
        return $this->json([
            "submit" => $this->generateUrl("location_edit", ["location" => $location->getId()]),
            "template" => $this->renderView("referential/location/modal/edit.html.twig", [
                "location" => $location,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{location}", name="location_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Location $location): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $client = isset($content->client) ? $manager->getRepository(Client::class)->find($content->client) : null;

        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $location) {
            $form->addError("label", "Un autre emplacement avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $location->setKiosk($content->type)
                ->setName($content->name)
                ->setClient($client)
                ->setActive($content->active)
                ->setDescription($content->description ?? null);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Emplacement modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{location}", name="location_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function deleteTemplate(Location $location): Response {
        return $this->json([
            "submit" => $this->generateUrl("location_delete", ["location" => $location->getId()]),
            "template" => $this->renderView("referential/location/modal/delete.html.twig", [
                "location" => $location,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{location}", name="location_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function delete(EntityManagerInterface $manager, Location $location): Response {
        if($location && (!$location->getTrackingMovements()->isEmpty() || !$location->getBoxes()->isEmpty())) {
            $location->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Emplacement <strong>{$location->getName()}</strong> désactivé avec succès"
            ]);
        } else if ($location) {
            $manager->remove($location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Emplacement <strong>{$location->getName()}</strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "L'emplacement n'existe pas"
            ]);
        }
    }

    /**
     * @Route("/export", name="locations_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $locations = $manager->getRepository(Location::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $locations) {
            foreach ($locations as $location) {
                $exportService->putLine($output, $location);
            }
        }, "export-emplacement-$today.csv", ExportService::LOCATION_HEADER);
    }

}
