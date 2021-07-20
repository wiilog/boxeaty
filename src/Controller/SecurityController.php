<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\Role;
use App\Entity\User;
use App\Form\PasswordForgottenType;
use App\Form\PasswordResetType;
use App\Security\Authenticator;
use App\Service\Mailer;
use DateTime;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\NativePasswordEncoder;
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
     * @Route("/login", name="login", options={"expose": true})
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
    public function register(Request $request, Mailer $mailer, UserPasswordHasherInterface $encoder): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute(Authenticator::HOME_ROUTE);
        }

        $user = new User();

        if ($request->getMethod() === "POST") {
            $em = $this->getDoctrine()->getManager();
            $userRepository = $em->getRepository(User::class);

            $valid = true;
            $email = $request->request->get("email");
            $password = $request->request->get("password");
            $existing = $userRepository->findBy(["email" => $email]);

            $user->setEmail($request->request->get("email"))
                ->setUsername($request->request->get("username"))
                ->addGroup($em->getRepository(Group::class)->find($request->request->get("group")));

            if ($existing) {
                $this->addFlash("danger", "Un utilisateur existe déjà avec cet email");
                $valid = false;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash("danger", "L'adresse email n'est pas valide");
                $valid = false;
            }

            if ($password !== $request->request->get("password-repeat")) {
                $this->addFlash("danger", "Les mots de passe sont différents");
                $valid = false;
            } else if (!Authenticator::isPasswordSecure($password)) {
                $this->addFlash("danger", Authenticator::PASSWORD_ERROR);
                $valid = false;
            }

            if ($valid) {
                $noAccess = $em->getRepository(Role::class)->findOneBy(["code" => Role::ROLE_NO_ACCESS]);

                $user->setPassword($encoder->hashPassword($user, $password))
                    ->setActive(true)
                    ->setRole($noAccess)
                    ->setCreationDate(new DateTime());

                $recipients = $userRepository->findNewUserRecipients($user->getGroups()->first());
                $mailer->send(
                    $recipients,
                    "BoxEaty - Nouvel utilisateur",
                    $this->renderView("emails/new_user.html.twig", [
                        "user" => $user,
                    ])
                );

                $em->persist($user);
                $em->flush();

                $this->addFlash("success", "Compte créé avec succès, un email a été envoyé aux responsables");

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
     * @Route("/mot-de-passe/perdu", name="password_forgotten")
     */
    public function passwordForgotten(Request $request, Mailer $mailer) {
        $form = $this->createForm(PasswordForgottenType::class, [
            "email" => $request->query->get("email")
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $forgotten = $form->getData();

            $manager = $this->getDoctrine()->getManager();
            $ur = $manager->getRepository(User::class);

            $user = $ur->findOneBy(["email" => $forgotten["email"]]);

            if ($user != null) {
                $token = bin2hex(random_bytes(16));
                $time = (new DateTime())->modify("+12 hours");

                $tokenEncoder = new NativePasswordEncoder();

                $user->setResetToken($tokenEncoder->encodePassword($token, $time->getTimestamp()));
                $user->setResetTokenExpiration($time);
                $manager->flush();

                $mailer->send($user, "BoxEaty - Réinitialisation du mot de passe", $this->renderView("emails/forgotten_password.html.twig", [
                    "user" => $user,
                    "token" => $token,
                ]));
            }

            $this->addFlash("success", "Un email a été envoyé au compte <b>{$forgotten["email"]}</b> s'il existe");

            return $this->redirectToRoute("password_forgotten_confirm", $forgotten);
        }

        return $this->render("security/password_forgotten.html.twig", [
            "form" => $form->createView()
        ]);
    }

    /**
     * @Route("/mot-de-passe/confirmation", name="password_forgotten_confirm")
     */
    public function passwordForgottenConfirm(Request $request) {
        return $this->render("security/password_forgotten_confirm.html.twig", $request->query->all());
    }

    /**
     * @Route("/mot-de-passe/reinitialiser/{user}", name="reset_password")
     */
    public function resetPassword(Request $request, UserPasswordEncoderInterface $encoder, User $user) {
        if (!$request->query->has("token")) {
            $this->addFlash("danger", "Lien de réinitialisation de mot de passe invalide");
            $this->redirectToRoute("login");
        }

        $token = $request->query->get("token");
        if (!$user->getResetToken() || !$user->getResetTokenExpiration() || $user->getResetTokenExpiration() < new DateTime()) {
            $this->addFlash("warning", "Le lien a expiré, merci de recommencer la procédure de réinitialisation de mot de passe");
            $this->redirectToRoute("password_forgotten");
        }

        $tokenEncoder = new NativePasswordEncoder();
        if (!$tokenEncoder->isPasswordValid($user->getResetToken(), $token, $user->getResetTokenExpiration()->getTimestamp())) {
            $this->addFlash("danger", "Le token de réinitialisation ne correspond pas");
            $this->redirectToRoute("login");
        }

        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $form->getData();

            if ($result["password"] != $result["confirmation"]) {
                $this->addFlash("danger", "Les mots de passe ne correspondent pas");
            } else if (!Authenticator::isPasswordSecure($result["password"])) {
                $this->addFlash("danger", Authenticator::PASSWORD_ERROR);
            } else {
                $manager = $this->getDoctrine()->getManager();

                $user->setPassword($encoder->encodePassword($user, $result["password"]));
                $user->setResetToken(null);
                $user->setResetTokenExpiration(null);
                $manager->flush();

                $this->addFlash("success", "Votre mot de passe a été changé");

                return $this->redirectToRoute("login");
            }
        }

        return $this->render("security/reset_password.html.twig", [
            "form" => $form->createView()
        ]);
    }

}
