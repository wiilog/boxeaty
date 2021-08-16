<?php

namespace App\Repository;

use App\Entity\PreparationLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method PreparationLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreparationLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreparationLine[]    findAll()
 * @method PreparationLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationLineRepository extends EntityRepository {

}
