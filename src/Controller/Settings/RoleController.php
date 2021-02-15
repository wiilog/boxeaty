<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Role;
use App\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;
use Helper\Form;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/roles")
 */
class RoleController extends AbstractController {

    /**
     * @Route("/liste", name="roles_list")
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function list(EntityManagerInterface $manager): Response {
        $roles = $manager->getRepository(Role::class)->findAll();

        return $this->render("settings/role/index.html.twig", [
            "roles" => $roles,
            "new_role" => new Role(),
        ]);
    }

    /**
     * @Route("/api", name="roles_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $roleRepository = $manager->getRepository(Role::class);
        $roles = $roleRepository->findForDatatable($request->request->all());
        $deletable = $roleRepository->getDeletable($roles["data"]);

        $data = [];
        foreach ($roles["data"] as $role) {
            $data[] = [
                "id" => $role->getId(),
                "label" => $role->getName(),
                "active" => $role->isActive() ? "Oui" : "Non",
                "actions" => $this->renderView("settings/role/datatable_actions.html.twig", [
                    "deletable" => $deletable[$role->getId()],
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $roles["total"],
            "recordsFiltered" => $roles["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="role_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function new(Request $request, EntityManagerInterface $manager): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Role::class)->findOneBy(["label" => $content->label]);
        if ($existing) {
            $form->addError("label", "Un rôle avec ce label existe déjà");
        }

        if ($form->isValid()) {
            $role = new Role();
            $role->setCode(strtoupper(StringHelper::slugify($content->label)))
                ->setName($content->label)
                ->setActive($content->active)
                ->setPermissions($content->permissions);

            $manager->persist($role);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{role}", name="role_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function editTemplate(EntityManagerInterface $manager, Role $role) {
        $roles = $manager->getRepository(Role::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("role_edit", ["role" => $role->getId()]),
            "template" => $this->renderView("settings/role/modal/edit_role.html.twig", [
                "role" => $role,
                "roles" => $roles,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{role}", name="role_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function edit(Request $request, EntityManagerInterface $manager, Role $role): Response {
        $form = Form::create();

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(Role::class)->findOneBy(["label" => $content->label]);
        if ($existing !== null && $existing !== $role) {
            $form->addError("label", "Un autre rôle avec ce label existe déjà");
        }

        if ($form->isValid()) {
            $role->setCode(strtoupper(StringHelper::slugify($content->label)))
                ->setName($content->label)
                ->setActive($content->active)
                ->setPermissions($content->permissions);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="role_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $role = $manager->getRepository(Role::class)->find($content->id);

        //TODO: check if role is used by users
        if ($role) {
            $manager->remove($role);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle {$role->getName()} supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Le rôle n'existe pas"
            ]);
        }
    }

}
