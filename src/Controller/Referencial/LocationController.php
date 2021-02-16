<?php

namespace App\Controller\Referencial;

use App\Annotation\HasPermission;
use App\Entity\Location;
use App\Entity\Role;
use App\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;
use Helper\Form;
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
    public function list(): Response
    {
        return $this->render("referencial/location/index.html.twig", [
            "new_location" => new Location(),
        ]);
    }

    /**
     * @Route("/api", name="locations_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $locations = $manager->getRepository(Location::class)
            ->findForDatatable($request->request->all());

        $data = [];
        foreach ($locations["data"] as $location) {
            $data[] = [
                "id" => $location->getId(),
                "name" => $location->getName(),
                "active" => $location->isActive() ? "Actif" : "Inactif",
                "description" => $location->getDescription(),
                "actions" => $this->renderView("referencial/location/datatable_actions.html.twig"),
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

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Un emplacement avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $location = new Location();
            $location
                ->setName(strtoupper(StringHelper::slugify($content->name)))
                ->setActive($content->active)
                ->setDescription($content->description);

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
    public function editTemplate(Location $location) {
        return $this->json([
            "submit" => $this->generateUrl("location_edit", ["location" => $location->getId()]),
            "template" => $this->renderView("referencial/location/modal/edit_location.html.twig", [
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

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Location::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $location) {
            $form->addError("label", "Un autre emplacement avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $location
                ->setName(strtoupper(StringHelper::slugify($content->name)))
                ->setActive($content->active)
                ->setDescription($content->description);

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
     * @Route("/supprimer", name="location_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_LOCATIONS)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $location = $manager->getRepository(Location::class)->find($content->id);

        if ($location) {
            $manager->remove($location);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Emplacement <strong>{$location->getName()}<strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "L'emplacement n'existe pas"
            ]);
        }
    }
}
