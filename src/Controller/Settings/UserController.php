<?php

namespace App\Controller\Settings;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/parametrage/utilisateurs")
 */
class UserController extends AbstractController {

    /**
     * @Route("/liste", name="users_list")
     */
    public function list(): Response {
        return $this->render("settings/user/index.html.twig", [

        ]);
    }

    /**
     * @Route("/api", name="users_api", options={"expose": true})
     */
    public function api(Request $request, EntityManagerInterface $manager): Response {
        $users = $manager->getRepository(User::class)
            ->findForDatatable($request->request->all());

        $data = [];
        foreach($users["data"] as $user) {
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
     * @Route("/delete", name="user_delete", options={"expose": true})
     */
    public function delete(Request $request, EntityManagerInterface $manager): Response {
        $content = json_decode($request->getContent());
        $user = $manager->getRepository(User::class)->find($content->id);

        if($user === $this->getUser()) {
            return $this->json([
                "success" => false,
                "msg" => "Vous ne pouvez pas supprimer votre propre compte utilisateur"
            ]);
        } else if($user) {
            //$manager->remove($user);
            //$manager->flush();

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
