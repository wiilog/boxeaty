<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

class UniqueNumberService {

    private const NUMBER_FORMAT = "YmdCCCC";

    /** @Required */
    public EntityManagerInterface $entityManager;

    public function createUniqueNumber(string $entity, ?EntityManagerInterface $manager = null): string {
        $date = new DateTime("now", new DateTimeZone("Europe/Paris"));
        $entityRepository = ($manager ?? $this->entityManager)->getRepository($entity);

        if(!method_exists($entityRepository, "getLastNumberByDate")) {
            throw new RuntimeException("Undefined getLastNumberByDate for $entity repository");
        }

        preg_match("/([^C]*)(C+)/", self::NUMBER_FORMAT, $matches);
        if(empty($matches)) {
            throw new RuntimeException("Invalid number format");
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $dateStr = $date->format($dateFormat);
        $lastNumber = $entityRepository->getLastNumberByDate($entity::PREFIX_NUMBER, $dateStr);

        $lastCounter = (
        (!empty($lastNumber) && $counterLen <= strlen($lastNumber))
            ? (int)substr($lastNumber, -$counterLen, $counterLen)
            : 0
        );
        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';

        return $entity::PREFIX_NUMBER . $dateStr . $currentCounterStr;
    }

}
