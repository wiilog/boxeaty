<?php

namespace App\Repository;

use App\Entity\DeliveryRound;
use Doctrine\ORM\EntityRepository;

/**
 * @method DeliveryRound|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRound|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryRound[]    findAll()
 * @method DeliveryRound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRoundRepository extends EntityRepository {

}
