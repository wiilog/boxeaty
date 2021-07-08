<?php

namespace App\Repository;

use App\Entity\DeliveryMethod;
use Doctrine\ORM\EntityRepository;

/**
 * @method DeliveryMethod|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryMethod|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryMethod[]    findAll()
 * @method DeliveryMethod[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryMethodRepository extends EntityRepository {

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("delivery_method")
            ->select("delivery_method.id AS id, delivery_method.name AS text")
            ->where("delivery_method.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
