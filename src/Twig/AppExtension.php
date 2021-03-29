<?php

namespace App\Twig;

use App\Helper\FormatHelper;
use App\Service\RoleService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension {

    /** @Required */
    public KernelInterface $kernel;

    /** @Required */
    public RoleService $roleService;

    /** @Required */
    public Environment $twig;

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
            new TwigFunction("base64", [$this, "base64"]),
            new TwigFunction("get_folder", [$this, "getFolder"]),
        ];
    }

    public function getFilters(): array {
        return [
            new TwigFilter("format_helper", [$this, "formatHelper"]),
        ];
    }

    public function menuConfiguration(): array {
        $config = [];

        foreach ($this->config as $item) {
            if ($this->shouldAddItem($item)) {
                if (isset($item["items"])) {
                    $item["items"] = array_filter($item["items"], fn($subitem) => $this->shouldAddItem($subitem));
                }

                if (!isset($item["items"]) || count($item["items"]) !== 0) {
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
        return $this->roleService->hasPermission(...$permissions);
    }

    public function getPermissions(): array {
        return $this->permissions;
    }

    public function base64(string $path) {
        $absolutePath = $this->kernel->getProjectDir() . "/public/$path";
        $type = pathinfo($absolutePath, PATHINFO_EXTENSION);
        $content = base64_encode(file_get_contents($absolutePath));

        if ($type == "svg") {
            $type = "svg+xml";
        }

        return "data:image/$type;base64,$content";
    }

    public function getFolder(string $folder) {
        foreach ($this->twig->getLoader()->getPaths() as $path) {
            if (is_dir("$path/$folder")) {
                $location = "$path/$folder";
                break;
            }
        }

        if (isset($location)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($location, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            $files = array_keys(iterator_to_array($files));

            return array_map(
                fn(string $path) => str_replace($this->kernel->getProjectDir() . "/templates", "", $path),
                $files
            );
        } else {
            return [];
        }
    }

    public function formatHelper($input, string $formatter, ...$options): string {
        return FormatHelper::{$formatter}($input, ...$options);
    }

}
