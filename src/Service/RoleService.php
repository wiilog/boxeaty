<?php

namespace App\Service;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Component\Security\Core\Security;

class RoleService {

    /** @Required */
    public Security $security;

    public function hasPermission(string ...$permissions): bool {
        $user = $this->security->getUser();
        if($user instanceof User && $user->isActive()) {
            $role = $user->getRole();

            $index = 0;
            $permissionCount = count($permissions);
            while($index < $permissionCount && !$this->hasSinglePermission($role, $permissions[$index])) {
                $index++;
            }

            return $index !== $permissionCount;
        }

        return false;
    }

    private function hasSinglePermission(Role $role, string $permission): bool {
        return in_array($permission, $role->getPermissions()) || (
                $permission === Role::ALLOW_EDIT_OWN_GROUP_ONLY && $role->isAllowEditOwnGroupOnly()
                || $permission === Role::DISPLAY_NEW_COUNTER_ORDER && $role->isShowCounterOrderScreen()
                || $permission === Role::REDIRECT_INDICATORS && $role->isRedirectIndicators()
                || $permission === Role::RECEIVE_MAILS_NEW_ACCOUNTS && $role->isReceiveMailsNewAccounts()
            );
    }

}
