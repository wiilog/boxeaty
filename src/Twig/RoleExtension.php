<?php

namespace App\Twig;

use App\Entity\User;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RoleExtension extends AbstractExtension {

    /** @Required */
    public Security $security;

    public function getFunctions(): array {
        return [
            new TwigFunction("has_permission", [$this, "hasPermission"])
        ];
    }

    public function hasPermission(string ...$permissions): bool {
        $user = $this->security->getUser();
        if($user && $user instanceof User && $user->isActive()) {
            foreach($permissions as $permission) {
                if(!in_array($permission, $user->getRole()->getPermissions())) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

}
