<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Depository;
use App\Entity\Role;
use App\Helper\Form;
use App\Repository\BoxTypeRepository;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/referentiel/depot")
 */
class DepositoryController extends AbstractController {

    /**
     * @Route("/liste", name="depositories_list")
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function list(Request $request, EntityManagerInterface $manager): Response {

        return $this->render("referential/depository/index.html.twig", [
            "new_depository" => new Depository(),
            "initial_depositories" => $this->api($request, $manager)->getContent(),
            "depositories_order" => BoxTypeRepository::DEFAULT_DATATABLE_ORDER,
        ]);
    }

    /**
     * @Route("/api", name="depositories_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $depositories = $manager->getRepository(Depository::class)
            ->findForDatatable(json_decode($request->getContent(), true) ?? []);

        $data = [];
        foreach ($depositories["data"] as $depository) {
            $data[] = [
                "id" => $depository->getId(),
                "name" => $depository->getName(),
                "active" => $depository->isActive() ? "Actif" : "Inactif",
                "description" => $depository->getDescription(),
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $depositories["total"],
            "recordsFiltered" => $depositories["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="depository_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(Depository::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Ce dépôt existe déjà");
        }

        if ($form->isValid()) {
            $depository = new Depository();
            $depository
                ->setName($content->name)
                ->setActive($content->active)
                ->setDescription($content->description);

            $manager->persist($depository);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Dépôt créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{depository}", name="depository_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function editTemplate(Depository $depository): Response {

        return $this->json([
            "submit" => $this->generateUrl("depository_edit", ["depository" => $depository->getId()]),
            "template" => $this->renderView("referential/depository/modal/edit.html.twig", [
                "depository" => $depository,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{depository}", name="depository_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Depository $depository): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(Depository::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $depository) {
            $form->addError("name", "Un autre dépôt avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $depository
                ->setName($content->name)
                ->setActive($content->active)
                ->setDescription($content->description);

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Dépôt modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{depository}", name="depository_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function deleteTemplate(Depository $depository): Response {
        return $this->json([
            "submit" => $this->generateUrl("depository_delete", ["depository" => $depository->getId()]),
            "template" => $this->renderView("referential/depository/modal/delete.html.twig", [
                "depository" => $depository,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{depository}", name="depository_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function delete(EntityManagerInterface $manager, Depository $depository): Response {
        if ($depository
            && (!$depository->getPreparations()->isEmpty()
                || !$depository->getLocations()->isEmpty()
                || !$depository->getClients()->isEmpty())
        ) {
            $depository->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Dépôt <strong>{$depository->getName()}</strong> désactivé avec succès",
            ]);
        } else if ($depository) {
            $manager->remove($depository);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Dépôt <strong>{$depository->getName()}</strong> supprimé avec succès",
            ]);
        } else {
            return $this->json([
                "success" => false,
                "message" => "Une erreur est survenue",
            ]);
        }
    }

    /**
     * @Route("/export", name="depositories_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_DEPOSITORIES)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $depositories = $manager->getRepository(Depository::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $depositories) {
            foreach ($depositories as $depository) {
                $exportService->putLine($output, $depository);
            }
        }, "export-depot-$today.csv", ExportService::DEPOSITORY_HEADER);
    }

}
