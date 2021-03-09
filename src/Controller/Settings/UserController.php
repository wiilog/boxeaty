<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Role;
use App\Entity\User;
use App\Helper\Form;
use App\Security\Authenticator;
use App\Service\ExportService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @Route("/parametrage/utilisateurs")
 */
class UserController extends AbstractController {

    /**
     * @Route("/liste", name="users_list")
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function list(EntityManagerInterface $manager): Response {
        $roles = $manager->getRepository(Role::class)->findBy(["active" => true]);

        return $this->render("settings/user/index.html.twig", [
            "new_user" => new User(),
            "roles" => $roles,
        ]);
    }

    /**
     * @Route("/api", name="users_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $users = $manager->getRepository(User::class)
            ->findForDatatable(json_decode($request->getContent(), true), $this->getUser());

        $data = [];
        foreach ($users["data"] as $user) {
            $data[] = [
                "id" => $user->getId(),
                "username" => $user->getUsername(),
                "email" => $user->getEmail(),
                "lastLogin" => $user->getLastLogin() ? $user->getLastLogin()->format("d/m/Y H:i") : "/",
                "role" => $user->getRole()->getName(),
                "status" => $user->isActive() ? "Actif" : "Inactif",
                "actions" => $this->renderView("datatable_actions.html.twig", [
                    "editable" => true,
                    "deletable" => true,
                ]),
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => $users["total"],
            "recordsFiltered" => $users["filtered"],
        ]);
    }

    /**
     * @Route("/nouveau", name="user_new", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function new(Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($existing) {
            $form->addError("email", "L'adresse email est déjà utilisée par un autre utilisateur");
        }

        $role = $manager->getRepository(Role::class)->find($content->role);
        if (!$role) {
            $form->addError("role", "Le rôle sélectionné n'existe plus, merci de rafraichir la page");
        }

        if (!Authenticator::isPasswordSecure($content->password)) {
            $form->addError("password", Authenticator::PASSWORD_ERROR);
        }

        $clients = $manager->getRepository(Client::class)->findBy(["id" => explode(",", $content->clients)]);
        $groups = $manager->getRepository(Group::class)->findBy(["id" => explode(",", $content->groups)]);

        if ($form->isValid()) {
            $user = new User();
            $user->setUsername($content->username)
                ->setEmail($content->email)
                ->setRole($role)
                ->setActive($content->active)
                ->setPassword($encoder->encodePassword($user, $content->password))
                ->setGroups($groups)
                ->setClients($clients)
                ->setCreationDate(new DateTime());

            $manager->persist($user);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur créé avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/modifier/template/{user}", name="user_edit_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function editTemplate(EntityManagerInterface $manager, User $user): Response {
        $roles = $manager->getRepository(Role::class)->findBy(["active" => true]);

        return $this->json([
            "submit" => $this->generateUrl("user_edit", ["user" => $user->getId()]),
            "template" => $this->renderView("settings/user/modal/edit.html.twig", [
                "user" => $user,
                "roles" => $roles,
            ])
        ]);
    }

    /**
     * @Route("/modifier/{user}", name="user_edit", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function edit(Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder, User $user): Response {
        $form = Form::create();

        $content = (object)$request->request->all();
        $existing = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($existing !== null && $existing !== $user) {
            $form->addError("email", "L'adresse email est déjà utilisée par un autre utilisateur");
        }

        $role = $manager->getRepository(Role::class)->find($content->role);
        if (!$role) {
            $form->addError("role", "Le rôle sélectionné n'existe plus, merci de rafraichir la page");
        }

        if (isset($content->password) && !Authenticator::isPasswordSecure($content->password)) {
            $form->addError("password", Authenticator::PASSWORD_ERROR);
        }

        $clients = $manager->getRepository(Client::class)->findBy(["id" => explode(",", $content->clients)]);
        $groups = $manager->getRepository(Group::class)->findBy(["id" => explode(",", $content->groups)]);

        if ($form->isValid()) {
            $user->setUsername($content->username)
                ->setEmail($content->email)
                ->setRole($role)
                ->setActive($content->active)
                ->setGroups($groups)
                ->setClients($clients)
                ->setCreationDate(new DateTime());

            if (isset($content->password)) {
                $user->setPassword($encoder->encodePassword($user, $content->password));
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur modifié avec succès",
                "menu" => $this->getUser() === $user ? $this->renderView("menu.html.twig", [
                    "current_route" => "users_list"
                ]) : null,
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer/template/{user}", name="user_delete_template", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function deleteTemplate(User $user): Response {
        return $this->json([
            "submit" => $this->generateUrl("user_delete", ["user" => $user->getId()]),
            "template" => $this->renderView("settings/user/modal/delete.html.twig", [
                "user" => $user,
            ])
        ]);
    }

    /**
     * @Route("/supprimer/{user}", name="user_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function delete(EntityManagerInterface $manager, User $user): Response {
        if ($user === $this->getUser()) {
            return $this->json([
                "success" => false,
                "msg" => "Vous ne pouvez pas supprimer votre propre compte utilisateur"
            ]);
        } else if($user
            && (
                !$user->getTrackingMovements()->isEmpty()
                || !$user->getOrderDepositTickets()->isEmpty()
            )) {
            $user->setActive(false);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur <strong>{$user->getUsername()}</strong> désactivé avec succès"
            ]);
        } else if ($user) {
            $manager->remove($user);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur <strong>{$user->getUsername()}</strong> supprimé avec succès"
            ]);
        }
    }

    /**
     * @Route("/export", name="users_export", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function export(EntityManagerInterface $manager, ExportService $exportService): Response {
        $users = $manager->getRepository(User::class)->iterateAll();

        $today = new DateTime();
        $today = $today->format("d-m-Y-H-i-s");

        return $exportService->export(function($output) use ($exportService, $users) {
            foreach ($users as $user) {
                $exportService->putLine($output, $user);
            }
        }, "export-utilisateurs-$today.csv", ExportService::USER_HEADER);
    }

}
