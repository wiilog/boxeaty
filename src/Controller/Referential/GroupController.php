<?php

namespace App\Controller\Referential;

use App\Annotation\HasPermission;
use App\Entity\Group;
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
 * @Route("/referentiel/groupes")
 */
class GroupController extends AbstractController {

    /**
     * @Route("/liste", name="groups_list")
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function list(): Response {
        return $this->render("referential/group/index.html.twig", [
            "new_group" => new Group(),
        ]);
    }

    /**
     * @Route("/api", name="groups_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $groups = $manager->getRepository(Group::class)
            ->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($groups["data"] as $group) {
            $data[] = [
                "id" => $group->getId(),
                "name" => $group->getName(),
                "establishment" => $group->getEstablishment(),
                "active" => $group->isActive() ? "Oui" : "Non",
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $groups["total"],
            "recordsFiltered" => $groups["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="group_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Group::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("email", "Ce groupe existe déjà");
        }

        if($form->isValid()) {
            $group = new Group();
            $group->setName($content->name)
                ->setEstablishment($content->establishment)
                ->setActive($content->active);

            $manager->persist($group);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{group}", name="group_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function editTemplate(Group $group): Response {
        return $this->json([
            "submit" => $this->generateUrl("group_edit", ["group" => $group->getId()]),
            "template" => $this->renderView("referential/group/modal/edit.html.twig", [
                "group" => $group,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{group}", name="group_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Group $group): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Group::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $group) {
            $form->addError("email", "Un autre groupe avec ce nom existe déjà");
        }

        if($form->isValid()) {
            $group->setName($content->name)
                ->setEstablishment($content->establishment)
                ->setActive($content->active);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Groupe modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/export", name="groups_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_GROUPS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $users = $manager->getRepository(Group::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $users) {
            foreach ($users as $user) {
                $exportService->putLine($output, $user);
            }
        }, "export-groupes-$today.csv", ExportService::GROUP_HEADER);
    }

}
