<?php

namespace App\Service;

class BoxStateService {

    public const STATE_BOX_AVAILABLE = 1;
    public const STATE_BOX_UNAVAILABLE = 2;
    public const STATE_BOX_CONSUMER = 3;
    public const STATE_BOX_CLIENT = 4;
    public const STATE_BOX_OUT = 5;
    public const STATE_RECORD_PACKING = 6;
    public const STATE_RECORD_UNPACKING = 7;
    public const STATE_BOX_IDENTIFIED = 8;

    public const BOX_STATES = [
        self::STATE_BOX_AVAILABLE => "Disponible",
        self::STATE_BOX_UNAVAILABLE => "Indisponible",
        self::STATE_BOX_CONSUMER => "Consommateur",
        self::STATE_BOX_CLIENT => "Client",
        self::STATE_BOX_OUT => "Sorti",
        self::STATE_BOX_IDENTIFIED => "Identifié"
    ];

    public const RECORD_STATES = [
        self::STATE_BOX_AVAILABLE => "Disponible",
        self::STATE_BOX_UNAVAILABLE => "Indisponible",
        self::STATE_BOX_CONSUMER => "Consommateur",
        self::STATE_BOX_CLIENT => "Client",
        self::STATE_BOX_OUT => "Sorti",
        self::STATE_BOX_IDENTIFIED => "Identifié",
        self::STATE_RECORD_PACKING => "Conditionné",
        self::STATE_RECORD_UNPACKING => "Déconditionné",
    ];

    public const LINKED_COLORS = [
        self::STATE_BOX_AVAILABLE => "success",
        self::STATE_BOX_UNAVAILABLE => "danger",
        self::STATE_BOX_CONSUMER => "primary",
        self::STATE_BOX_CLIENT => "warning",
        self::STATE_BOX_OUT => "secondary",
        self::STATE_RECORD_PACKING => "info",
        self::STATE_RECORD_UNPACKING => "dark",
    ];

    public const DEFAULT_COLOR = 'dark';

}
