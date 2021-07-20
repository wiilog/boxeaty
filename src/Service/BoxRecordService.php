<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\Location;
use App\Entity\User;
use DateTime;

class BoxRecordService {

    /**
     * @return BoxRecord[]
     */
    public function generateBoxRecords(Box $box, array $olderValues, ?User $loggedUser, Box $crate = null): array {
        /** @var Location $oldLocation */
        $oldLocation = $olderValues['location'] ?? null;
        $oldLocationId = $oldLocation ? $oldLocation->getId() : null;
        $oldState = $olderValues['state'] ?? null;
        $oldComment = $olderValues['comment'] ?? null;

        $newLocationId = $box->getLocation() ? $box->getLocation()->getId() : null;
        $newState = $box->getState();
        $newComment = $box->getComment();

        if ($newLocationId != $oldLocationId) {
            $tracking = $this->createBoxRecord($box, true, $crate);
            $tracking->setUser($loggedUser);
        }

        if ($newState != $oldState || $newComment != $oldComment) {
            $record = $this->createBoxRecord($box, false, $crate);
            $record->setUser($loggedUser);
        }

        return [$tracking ?? null, $record ?? null];
    }

    private function createBoxRecord(Box $box, bool $trackingMovement, ?Box $crate = null): BoxRecord {
        return (new BoxRecord())
            ->setDate(new DateTime())
            ->setTrackingMovement($trackingMovement)
            ->copyBox($box, $crate);
    }

}
