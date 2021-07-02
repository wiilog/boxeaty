<?php

namespace App\Repository;

use App\Entity\OrderStatusHistory;
use Doctrine\ORM\EntityRepository;

/**
 * @method OrderStatusHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderStatusHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderStatusHistory[]    findAll()
 * @method OrderStatusHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderStatusHistoryRepository extends EntityRepository {

}
