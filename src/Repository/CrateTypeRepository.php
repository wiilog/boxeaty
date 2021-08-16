<?php

namespace App\Repository;

use App\Entity\ClientBoxType;
use Doctrine\ORM\EntityRepository;

/**
 * @method ClientBoxType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientBoxType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientBoxType[]    findAll()
 * @method ClientBoxType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CrateTypeRepository extends EntityRepository {

}
