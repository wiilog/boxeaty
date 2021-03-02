<?php

namespace App\Listener;

use App\Entity\TrackingMovement;

class MovementListener {

    public function prePersist(TrackingMovement $movement) {
        $location = $movement->getLocation();
        $box = $movement->getBox();

        $location->setDeposits($location->getDeposits() + 1);
        if($location->isKiosk()) {
            $box->setUses($box->getUses() + 1);
        }
    }

}
