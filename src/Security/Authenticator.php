<?php

namespace App\Security;

use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class Authenticator extends AbstractFormLoginAuthenticator {

    use TargetPathTrait;

    public const LOGIN_ERROR = "Identifiants incorrects";
    public const LOGIN_ROUTE = "login";
    public const HOME_ROUTE = "home";
    public const INDICATORS_ROUTE = "indicators_index";

    public const PASSWORD_ERROR = "Le mot de passe doit contenir au moins un chiffre, une majuscule et une minuscule. Il doit faire au moins 8 caractères.";

    /** @Required */
    public EntityManagerInterface $entityManager;

    /** @Required */
    public UrlGeneratorInterface $urlGenerator;

    /** @Required */
    public CsrfTokenManagerInterface $csrfTokenManager;

    /** @Required */
    public UserPasswordEncoderInterface $encoder;

    public function supports(Request $request): bool {
        return self::LOGIN_ROUTE === $request->attributes->get("_route") && $request->isMethod("POST");
    }

    public function getCredentials(Request $request): array {
        $credentials = [
            "email" => $request->request->get("email"),
            "password" => $request->request->get("password"),
            "csrf_token" => $request->request->get("_csrf_token"),
        ];

        $request->getSession()->set(Security::LAST_USERNAME, $credentials["email"]);

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider): ?User {
        $token = new CsrfToken("authenticate", $credentials["csrf_token"]);
        if(!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $credentials["email"]]);
        if(!$user) {
            throw new CustomUserMessageAuthenticationException(self::LOGIN_ERROR);
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user): bool {
        if($user instanceof User) {
            $valid = $this->encoder->isPasswordValid($user, $credentials["password"]);
            if($valid && !$user->isActive()) {
                throw new CustomUserMessageAuthenticationException("Votre compte est inactif");
            }

            return $valid;
        }

        return false;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): RedirectResponse {
        if($token->getUser() instanceof User) {
            $token->getUser()->setLastLogin(new DateTime());
            $this->entityManager->flush();

            if($token->getUser()->getRole()->isRedirectIndicators()) {
                return new RedirectResponse($this->urlGenerator->generate(self::INDICATORS_ROUTE));
            }
        }

        if($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate(self::HOME_ROUTE));
    }

    protected function getLoginUrl(): string {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public static function isPasswordSecure(string $password): bool {
        return strlen($password) >= 8 &&
            preg_match("/[A-Z]/", $password) &&
            preg_match("/[a-z]/", $password) &&
            preg_match("/[0-9]/", $password);
    }

}
