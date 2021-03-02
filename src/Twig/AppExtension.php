<?php

namespace App\Twig;

use App\Entity\User;
use App\Helper\Stream;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension {

    /** @Required */
    public Security $security;

    private array $config;
    private array $permissions;

    public function __construct($config, $permissions) {
        $this->config = $config;
        $this->permissions = $permissions;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction("menu_configuration", [$this, "menuConfiguration"]),
            new TwigFunction("has_permission", [$this, "hasPermission"]),
            new TwigFunction("permissions", [$this, "getPermissions"]),
        ];
    }

    public function menuConfiguration(): array {
        $config = [];

        foreach($this->config as $item) {
            if($this->shouldAddItem($item)) {
                if(isset($item["items"])) {
                    $item["items"] = array_filter($item["items"], fn($subitem) => $this->shouldAddItem($subitem));
                }

                if(!isset($item["items"]) || count($item["items"]) !== 0) {
                    $config[] = $item;
                }
            }
        }

        return $config;
    }

    private function shouldAddItem(array $item): bool {
        return !isset($item["permission"]) || $this->hasPermission(constant($item["permission"]));
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
