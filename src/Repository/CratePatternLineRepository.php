<?php

namespace App\Repository;

use App\Entity\CratePatternLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method CratePatternLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method CratePatternLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method CratePatternLine[]    findAll()
 * @method CratePatternLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CratePatternLineRepository extends EntityRepository {

}
