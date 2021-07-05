<?php

namespace App\Repository;

use App\Entity\ClientOrder;
use Doctrine\ORM\EntityRepository;

/**
 * @method ClientOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientOrder[]    findAll()
 * @method ClientOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientOrderRepository extends EntityRepository {

    /**
     * @return ClientOrder[]
     */
    public function findBetween($from, $to): array {
        return $this->createQueryBuilder("client_order")
            ->where("client_order.expectedDelivery BETWEEN :from AND :to")
            ->orderBy("client_order.expectedDelivery", "ASC")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->getQuery()
            ->getResult();
    }

}
