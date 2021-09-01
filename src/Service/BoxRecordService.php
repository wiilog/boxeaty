<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class BoxRecordService {

    /** @Required */
    public EntityManagerInterface $manager;

    /**
     * @return BoxRecord[]
     */
    public function generateBoxRecords(Box $box, ?Box $previous, ?User $user = null, ?DateTime $date = null): array {
        $currentValues = $this->extract($box);
        $previousValues = $this->extract($previous);

        if ($previousValues["location"] != $currentValues["location"]) {
            $tracking = $this->createBoxRecord($box, true, $date);
            $tracking->setUser($user);
        }

        if ($previousValues["state"] != $currentValues["state"] ||
            $previousValues["crate"] != $currentValues["crate"] ||
            $previousValues["quality"] != $currentValues["quality"] ||
            $previousValues["comment"] != $currentValues["comment"]) {
            $record = $this->createBoxRecord($box, false, $date);
            $record->setUser($user);
        }

        return [$tracking ?? null, $record ?? null];
    }

    public function persist(Box $box, ?BoxRecord $record, EntityManagerInterface $manager = null) {
        if ($record) {
            $record->setBox($box);
            ($manager ?? $this->manager)->persist($record);
        }
    }

    private function createBoxRecord(Box $box, bool $trackingMovement, ?DateTime $date = null): BoxRecord {
        return (new BoxRecord())
            ->setDate($date ?? new DateTime())
            ->setTrackingMovement($trackingMovement)
            ->copyBox($box);
    }

    private function extract(?Box $box): array {
        return [
            "crate" => $box && $box->getCrate() ? $box->getCrate()->getId() : null,
            "location" => $box && $box->getLocation() ? $box->getLocation()->getId() : null,
            "state" => $box ? $box->getState() : null,
            "quality" => $box && $box->getQuality() ? $box->getQuality()->getId() : null,
            "comment" => $box ? $box->getComment() : null,
        ];
    }

}
