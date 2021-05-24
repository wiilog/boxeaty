<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Helper\FormatHelper;
use WiiCommon\Helper\StringHelper;
use App\Repository\RoleRepository;
use App\Service\ExportService;
use DateTime;
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
    public function list(Request $request, EntityManagerInterface $manager): Response {
        return $this->render("settings/role/index.html.twig", [
            "new_role" => new Role(),
            "initial_roles" => $this->api($request, $manager)->getContent(),
            "roles_order" => RoleRepository::DEFAULT_DATATABLE_ORDER,
        ]);
    }

    /**
     * @Route("/api", name="roles_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function api(Request $request,
                        EntityManagerInterface $manager): Response {
        $roleRepository = $manager->getRepository(Role::class);
        $roles = $roleRepository->findForDatatable(json_decode($request->getContent(), true) ?? []);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $actions = $this->renderView("datatable_actions.html.twig", [
            "editable" => true,
            "deletable" => true,
        ]);

        $data = [];
        /** @var Role $role */
        foreach ($roles["data"] as $role) {
            $editable = $currentUser->getRole()->getCode() === Role::ROLE_ADMIN
                || ($role->getCode() !== Role::ROLE_ADMIN && $role->getId() !== $currentUser->getRole()->getId());

            $data[] = [
                "id" => $role->getId(),
                "name" => $role->getName(),
                "active" => FormatHelper::bool($role->isActive()),
                "actions" => $editable ? $actions : "",
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
                ->setShowNewOrderOnHome($content->showNewOrderOnHome)
                ->setReceiveMailsNewAccounts($content->receiveMailsNewAccounts);

            if(count($role->getPermissions()) === 0) {
                return $this->json([
                    "success" => false,
                    "message" => "Vous devez sélectionner au moins une permission",
                ]);
            }

            $manager->persist($role);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Rôle créé avec succès",
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

            /** @var User $currentUser */
            $currentUser = $this->getUser();

            if ($role->getCode() === Role::ROLE_ADMIN &&
                $currentUser->getRole()->getCode() !== Role::ROLE_ADMIN) {
                return $this->json([
                    "success" => false,
                    "message" => "Vous n'avez pas les permissions nécessaires",
                ]);
            }

            //don't edit slug for the base roles
            if(!in_array($role->getCode(), [Role::ROLE_NO_ACCESS, Role::ROLE_ADMIN])) {
                $role->setCode(strtoupper(StringHelper::slugify($content->name)));
            }

            $role->setName($content->name)
                ->setActive($content->active)
                ->setPermissions(explode(",", $content->permissions))
                ->setAllowEditOwnGroupOnly($content->allowEditOwnGroupOnly)
                ->setShowNewOrderOnHome($content->showNewOrderOnHome)
                ->setReceiveMailsNewAccounts($content->receiveMailsNewAccounts);

            if(count($role->getPermissions()) === 0) {
                return $this->json([
                    "success" => false,
                    "message" => "Vous devez sélectionner au moins une permission",
                ]);
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Rôle modifié avec succès",
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
                "message" => "Rôle <strong>{$role->getName()}</strong> désactivé avec succès"
            ]);
        } else if ($role) {
            $manager->remove($role);
            $manager->flush();

            return $this->json([
                "success" => true,
                "message" => "Rôle <strong>{$role->getName()}</strong> supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "reload" => true,
                "message" => "Le rôle n'existe pas"
            ]);
        }
    }

    /**
     * @Route("/export", name="roles_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_ROLES)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $roles = $manager->getRepository(Role::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $roles) {
            foreach ($roles as $role) {
                $exportService->putLine($output, $role);
            }
        }, "export-role-$today.csv", ExportService::ROLE_HEADER);
    }

}
