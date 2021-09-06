<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("METHOD")
 */
class HasPermission {

    public const IN_RENDER = 0;
    public const IN_JSON = 1;

    public $value;

    public int $mode = self::IN_RENDER;

}
