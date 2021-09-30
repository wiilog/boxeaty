<?php

namespace App\Repository;

use App\Entity\OrderType;
use Doctrine\ORM\EntityRepository;

/**
 * @method OrderType|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderType|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderType[]    findAll()
 * @method OrderType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderTypeRepository extends EntityRepository {

    public function findSelectable() {
        return $this->createQueryBuilder("order_type")
            ->andWhere("order_type.code IN (:codes)")
            ->setParameter("codes", OrderType::SELECTABLE)
            ->getQuery()
            ->getResult();
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("order_type")
            ->select("order_type.id AS id, order_type.name AS text")
            ->andWhere("order_type.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
