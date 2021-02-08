<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController {

    /**
     * @Route("/", name="default")
     */
    public function default(): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute("home");
        } else {
            return $this->redirectToRoute("login");
        }
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute("home");
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render("security/login.html.twig", [
            "last_username" => $lastUsername,
            "error" => $error
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(): Response {
        throw new \LogicException("This method can be blank - it will be intercepted by the logout key on your firewall.");
    }

    /**
     * @Route("/reinitialiser-mot-de-passe", name="password_forgotten")
     */
    public function resetPassword(): Response {
        return $this->render("security/password_forgotten.html.twig");
    }

}
