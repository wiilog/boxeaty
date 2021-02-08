<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterType;
use App\Security\Authenticator;
use DateTime;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController {

    /**
     * @Route("/", name="default")
     */
    public function default(): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute(Authenticator::HOME_ROUTE);
        } else {
            return $this->redirectToRoute(Authenticator::LOGIN_ROUTE);
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
     * @Route("/register", name="register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute(Authenticator::HOME_ROUTE);
        }

        $user = new User();

        if($request->getMethod() === "POST") {
            $em = $this->getDoctrine()->getManager();

            $valid = true;
            $email = $request->request->get("email");
            $password = $request->request->get("password");
            $existing = $em->getRepository(User::class)->findByLogin($email);

            $user->setEmail($email);

            if($existing) {
                $this->addFlash("danger", "Un utilisateur existe déjà avec cet email");
                $valid = false;
            }

            if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash("danger", "L'adresse email n'est pas valide");
                $valid = false;
            }

            if($password !== $request->request->get("password-repeat")) {
                $this->addFlash("danger", "Les mots de passe sont différents");
                $valid = false;
            }

            if($valid) {
                $user->setEmail($email)
                    ->setPassword($encoder->encodePassword($user, $password))
                ->setActive(true)
                ->setCreationDate(new DateTime());

                $em->persist($user);
                $em->flush();

                $this->addFlash("success", "Compte créé avec succès");

                return $this->redirectToRoute("login");
            }
        }

        return $this->render("security/register.html.twig", [
            "user" => $user,
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(): Response {
        throw new LogicException("This method can be blank - it will be intercepted by the logout key on your firewall.");
    }

    /**
     * @Route("/reinitialiser-mot-de-passe", name="password_forgotten")
     */
    public function resetPassword(): Response {
        return $this->render("security/password_forgotten.html.twig");
    }

}
