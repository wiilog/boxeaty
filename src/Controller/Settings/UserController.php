<?php

namespace App\Controller\Settings;

use App\Annotation\HasPermission;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Helper\Form;
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
        $roles = $manager->getRepository(Role::class)->findAll();

        return $this->render("settings/user/index.html.twig", [
            "roles" => $roles
        ]);
    }

    /**
     * @Route("/api", name="users_api", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $users = $manager->getRepository(User::class)
            ->findForDatatable($request->request->all());

        $data = [];
        foreach ($users["data"] as $user) {
            $data[] = [
                "id" => $user->getId(),
                "username" => $user->getUsername(),
                "email" => $user->getEmail(),
                "lastLogin" => $user->getLastLogin() ? $user->getLastLogin()->format("d/m/Y H:i") : "/",
                "role" => "Administrateur",
                "actions" => $this->renderView("settings/user/datatable_actions.html.twig"),
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

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($existing) {
            $form->addError("email", "L'adresse email est déjà utilisée par un autre utilisateur");
        }

        $role = $manager->getRepository(Role::class)->find($content->role);
        if (!$role) {
            $form->addError("role", "Le rôle sélectionné n'existe plus, merci de rafraichir la page");
        }

        if($form->isValid()) {
            //TODO: set group and location
            $user = new User();
            $user->setUsername($content->username)
                ->setEmail($content->email)
                ->setRole($role)
                ->setActive($content->active)
                ->setPassword($encoder->encodePassword($user, $content->password))
                ->setCreationDate(new \DateTime());

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
    public function editTemplate(EntityManagerInterface $manager, User $user) {
        $roles = $manager->getRepository(Role::class)->findAll();

        return $this->json([
            "submit" => $this->generateUrl("user_edit", ["user" => $user->getId()]),
            "template" => $this->renderView("settings/user/modal/edit_user.html.twig", [
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

        $content = json_decode($request->getContent());
        $existing = $manager->getRepository(User::class)->findOneBy(["email" => $content->email]);
        if ($existing !== null && $existing !== $user) {
            $form->addError("email", "L'adresse email est déjà utilisée par un autre utilisateur");
        }

        $role = $manager->getRepository(Role::class)->find($content->role);
        if (!$role) {
            $form->addError("role", "Le rôle sélectionné n'existe plus, merci de rafraichir la page");
        }

        if($form->isValid()) {
            //TODO: set group and location
            $user->setUsername($content->username)
                ->setEmail($content->email)
                ->setRole($role)
                ->setActive($content->active)
                ->setCreationDate(new \DateTime());

            if($content->password) {
                $user->setPassword($encoder->encodePassword($user, $content->password));
            }

            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur modifié avec succès",
            ]);
        } else {
            return $form->errors();
        }
    }

    /**
     * @Route("/supprimer", name="user_delete", options={"expose": true})
     * @HasPermission(Role::MANAGE_USERS)
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $user = $manager->getRepository(User::class)->find($content->id);

        if ($user === $this->getUser()) {
            return $this->json([
                "success" => false,
                "msg" => "Vous ne pouvez pas supprimer votre propre compte utilisateur"
            ]);
        } else if ($user) {
            $manager->remove($user);
            $manager->flush();

            return $this->json([
                "success" => true,
                "msg" => "Utilisateur {$user->getUsername()} supprimé avec succès"
            ]);
        } else {
            return $this->json([
                "success" => false,
                "msg" => "L'utilisateur n'existe pas"
            ]);
        }
    }

}
