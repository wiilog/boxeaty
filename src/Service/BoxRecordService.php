<?php

namespace App\Service;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class BoxRecordService {

    private const TRACKING_MOVEMENT_FIELDS = ["location"];
    private const BOX_HISTORY_FIELDS = ["state", "crate", "quality", "comment"];

    /** @Required */
    public EntityManagerInterface $manager;

    /**
     * @return BoxRecord[]
     */
    public function generateBoxRecords(Box $box, ?Box $previous, ?User $user = null, ?callable $callback = null): array {
        $date = new DateTime();
        $currentValues = $this->extract($box);
        $previousValues = $this->extract($previous);

        if($this->isDifferent($currentValues, $previousValues, self::TRACKING_MOVEMENT_FIELDS)) {
            $tracking = $this->createBoxRecord($box, true, $user, $date);
            $this->persist($box, $tracking);

            if($callback) {
                $callback($tracking);
            }
        }

        if($this->isDifferent($currentValues, $previousValues, self::BOX_HISTORY_FIELDS)) {
            $record = $this->createBoxRecord($box, false, $user, $date);
            $this->persist($box, $record);

            if($callback) {
                $callback($record);
            }
        }

        return [$tracking ?? null, $record ?? null];
    }

    public function persist(Box $box, ?BoxRecord $record, EntityManagerInterface $manager = null) {
        if($record) {
            $record->setBox($box);
            ($manager ?? $this->manager)->persist($record);
        }
    }

    public function remove(?BoxRecord $record, EntityManagerInterface $manager = null) {
        if($record) {
            $record->setBox(null);
            ($manager ?? $this->manager)->remove($record);
        }
    }

    private function createBoxRecord(Box $box, bool $trackingMovement, ?User $user, ?DateTime $date = null): BoxRecord {
        return (new BoxRecord())
            ->setDate($date ?? new DateTime())
            ->setUser($user)
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

    private function isDifferent(array $a, array $b, array $fields): bool {
        foreach($fields as $field) {
            if($a[$field] != $b[$field]) {
                return true;
            }
        }

        return false;
    }

}
