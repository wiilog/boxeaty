<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Role;
use App\Helper\Form;
use App\Helper\StringHelper;
use Doctrine\ORM\EntityManagerInterface;
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
    public function list(): Response {
        return $this->render("settings/role/index.html.twig", [
            "new_role" => new Role(),
        ]);
    }

    /**
     * @Route("/api", name="roles_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $roleRepository = $manager->getRepository(Role::class);
        $roles = $roleRepository->findForDatatable(json_decode($request->getContent(), true));

        $data = [];
        foreach ($roles["data"] as $role) {
            $data[] = [
                "id" => $role->getId(),
                "name" => $role->getName(),
                "active" => $role->isActive() ? "Oui" : "Non",
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
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

        $content = (object) $request->request->all();
        $existing = $manager->getRepository(Role::class)->findOneBy(["name" => $content->name]);
        if ($existing) {
            $form->addError("name", "Un rôle avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            $role = new Role();
            $role->setCode(strtoupper(StringHelper::slugify($content->name)))
                ->setName($content->name)
                ->setActive($content->active)
                ->setPermissions(explode(",", $content->permissions))
                ->setAllowEditOwnGroupOnly($content->allowEditOwnGroupOnly)
                ->setRedirectUserNewCommand($content->redirectUserNewCommand)
                ->setReceiveMailsNewAccounts($content->receiveMailsNewAccounts);

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
    public function editTemplate(EntityManagerInterface $manager, Role $role): Response {
        $roles = $manager->getRepository(Role::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("role_edit", ["role" => $role->getId()]),
            "template" => $this->renderView("settings/role/modal/edit.html.twig", [
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

        $content = (object) $request->request->all();
        $existing = $manager->getRepository(Role::class)->findOneBy(["name" => $content->name]);
        if ($existing !== null && $existing !== $role) {
            $form->addError("name", "Un autre rôle avec ce nom existe déjà");
        }

        if ($form->isValid()) {
            //don't edit slug for the base roles
            if(!in_array($role->getCode(), [Role::ROLE_NO_ACCESS, Role::ROLE_ADMIN])) {
                $role->setCode(strtoupper(StringHelper::slugify($content->name)));
            }

            $role->setName($content->name)
                ->setActive($content->active)
                ->setPermissions(explode(",", $content->permissions))
                ->setAllowEditOwnGroupOnly($content->allowEditOwnGroupOnly)
                ->setRedirectUserNewCommand($content->redirectUserNewCommand)
                ->setReceiveMailsNewAccounts($content->receiveMailsNewAccounts);

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle modifié avec succès",
                "menu" => $this->getUser()->getRole() === $role ? $this->renderView("menu.html.twig", [
                    "current_route" => "roles_list"
                ]) : null,
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{role}", name="role_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function deleteTemplate(Role $role): Response {
        return $this->json([
            "submit" => $this->generateUrl("role_delete", ["role" => $role->getId()]),
            "template" => $this->renderView("settings/role/modal/delete.html.twig", [
                "role" => $role,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{role}", name="role_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function delete(EntityManagerInterface $manager, Role $role): Response {
        if(!$role->getUsers()->isEmpty()) {
            $role->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle <strong>{$role->getName()}</strong> désactivé avec succès"
            ]);
        } else if ($role) {
            $manager->remove($role);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Rôle <strong>{$role->getName()}</strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "Le rôle n'existe pas"
            ]);
        }
    }

}
