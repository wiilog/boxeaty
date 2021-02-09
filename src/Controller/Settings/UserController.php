<?php

namespace App\Controller\Settings;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function api(): Response {
        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        $data = [];
        foreach($users as $user) {
            $data[] = [
                "email" => $user->getEmail(),
                "last_login" => $user->getLastLogin() ? $user->getLastLogin()->format("d/m/Y H:i") : "/",
                "role" => "Administrateur",
                "actions" => '<span style="display:block;text-align:right"><i class="fas fa-ellipsis-v"></i></span>',
            ];
        }

        return $this->json([
            "data" => $data,
            "recordsTotal" => count($users),
            "recordsFiltered" => count($users),
        ]);
    }

}
