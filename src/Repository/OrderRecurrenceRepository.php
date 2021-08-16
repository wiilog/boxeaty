<?php

namespace App\Repository;

use App\Entity\OrderRecurrence;
use Doctrine\ORM\EntityRepository;

/**
 * @method OrderRecurrence|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderRecurrence|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderRecurrence[]    findAll()
 * @method OrderRecurrence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRecurrenceRepository extends EntityRepository {
}
