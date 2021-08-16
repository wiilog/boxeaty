<?php

namespace App\Repository;

use App\Entity\ClientOrderLine;
use Doctrine\ORM\EntityRepository;

/**
 * @method ClientOrderLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientOrderLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientOrderLine[]    findAll()
 * @method ClientOrderLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientOrderLineRepository extends EntityRepository {

}
