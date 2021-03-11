<?php


namespace App\Service;


use App\Entity\User;
use Symfony\Component\Security\Core\Security;

class RoleService {

    /** @Required */
    public Security $security;

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