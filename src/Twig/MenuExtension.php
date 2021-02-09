<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension {

    private array $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function getFunctions(): array {
        return [
            new TwigFunction("menu_configuration", [$this, "menuConfiguration"])
        ];
    }

    public function menuConfiguration(): array {
        return $this->config;
    }

}
