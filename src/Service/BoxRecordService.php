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
    public function generateBoxRecords(Box $box,
                                       array $olderValues,
                                       ?User $loggedUser): array {
        /** @var Location $oldLocation */
        $oldLocation = $olderValues['location'] ?? null;
        $oldLocationId = $oldLocation ? $oldLocation->getId() : null;
        $oldState = $olderValues['state'] ?? null;
        $oldComment = $olderValues['comment'] ?? null;

        $newLocationId = $box->getLocation() ? $box->getLocation()->getId() : null;
        $newState = $box->getState();
        $newComment = $box->getComment();

        if ($newLocationId != $oldLocationId) {
            $tracking = $this->createBoxRecord(true);
            $tracking->setUser($loggedUser);
        }
        else {
            $tracking = null;
        }

        if ($newState != $oldState
            || $newComment != $oldComment) {
            $record = $this->createBoxRecord(false);
            $record->setUser($loggedUser);
        }
        else {
            $record = null;
        }

        return [$tracking, $record];
    }

    private function createBoxRecord(bool $trackingMovement): BoxRecord {
        return (new BoxRecord())
            ->setDate(new DateTime())
            ->setTrackingMovement($trackingMovement)
            ->copyBox();
    }
}
