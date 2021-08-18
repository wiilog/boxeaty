<?php

namespace App\Service;

use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Entity;
use Exception;

class UniqueNumberService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * getLastNumberByPrefixAndDate() function must be implemented in current entity repository with $prefix and $date params
     * @param EntityManagerInterface $entityManager
     * @param string $prefix - Prefix of the entity unique number => Available in chosen entity
     * @param string $entity - Chosen entity to generate unique number => Format Entity::class
     * @return string
     * @throws Exception
     */
    public function createUniqueNumber(EntityManagerInterface $entityManager,
                                       string $prefix,
                                       string $entity): string {

        $format = 'YmdCCCC';
        $date = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $entityRepository = $entityManager->getRepository($entity);

        if (!method_exists($entityRepository, 'getLastNumberByDate')) {
            throw new Exception("Undefined getLastNumberByDate for $entity " . "repository");
        }

        preg_match('/([^C]*)(C+)/', $format, $matches);
        if (empty($matches)) {
            throw new Exception('Invalid number format');
        }

        $dateFormat = $matches[1];
        $counterFormat = $matches[2];
        $counterLen = strlen($counterFormat);

        $dateStr = $date->format(substr($format, 0, -1 * $counterLen));
        $lastNumber = $entityRepository->getLastNumberByDate($dateStr);

        $lastCounter = (
        (!empty($lastNumber) && $counterLen <= strlen($lastNumber))
            ? (int) substr($lastNumber, -$counterLen, $counterLen)
            : 0
        );
        $currentCounterStr = sprintf("%0{$counterLen}u", $lastCounter + 1);
        $dateStr = !empty($dateFormat) ? $date->format($dateFormat) : '';
        $smartPrefix = !empty($prefix) ? $prefix : '';

        return ($smartPrefix . $dateStr . $currentCounterStr);
    }
}
