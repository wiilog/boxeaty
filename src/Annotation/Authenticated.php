<?php

namespace App\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Authenticated {

    public const KIOSK = 1;
    public const MOBILE = 2;

    public int $value;

}
