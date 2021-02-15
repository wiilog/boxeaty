<?php

namespace App\Twig;

use App\Entity\User;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RoleExtension extends AbstractExtension {

    /** @Required */
    public Security $security;

    private array $permissions;

    public function __construct($permissions) {
        $this->permissions = $permissions;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction("has_permission", [$this, "hasPermission"]),
            new TwigFunction("permissions", [$this, "getPermissions"]),
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

    public function getPermissions(): array {
        return $this->permissions;
    }

}
