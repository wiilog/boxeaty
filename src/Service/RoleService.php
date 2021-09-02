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
        if ($user && $user instanceof User && $user->isActive()) {
            $role = $user->getRole();

            $hasPermission = false;
            $permissionCount = count($permissions);
            $index = 0;

            // if the user has at least one permission we return true
            while (!$hasPermission
                   && $index < $permissionCount) {
                $permission = $permissions[$index];
                $hasPermission = (
                    (in_array($permission, $role->getPermissions()))
                    || (
                        in_array($permission, Role::ADDITIONAL_PERMISSIONS)
                        && (
                            $permission === Role::ALLOW_EDIT_OWN_GROUP_ONLY && $role->isAllowEditOwnGroupOnly()
                            || $permission === Role::REDIRECT_NEW_COUNTER_ORDER && $role->getRedirectNewCounterOrder()
                            || $permission === Role::SHOW_INDICATORS_ON_HOME && $role->getShowIndicatorsOnHome()
                            || $permission === Role::RECEIVE_MAILS_NEW_ACCOUNTS && $role->isReceiveMailsNewAccounts()
                        )
                    )
                );
                $index++;
            }

            return $hasPermission;
        } else {
            return false;
        }
    }

}
