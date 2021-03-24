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

            foreach ($permissions as $permission) {
                if(in_array($permission, Role::ADDITIONAL_PERMISSIONS)) {
                    if($permission === Role::ALLOW_EDIT_OWN_GROUP_ONLY && !$role->isAllowEditOwnGroupOnly()
                        || $permission === Role::REDIRECT_USER_NEW_COMMAND && !$role->isRedirectUserNewCommand()
                        || $permission === Role::RECEIVE_MAILS_NEW_ACCOUNTS && !$role->isReceiveMailsNewAccounts()) {
                        return false;
                    }
                } else if(!in_array($permission, $role->getPermissions())) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

}
