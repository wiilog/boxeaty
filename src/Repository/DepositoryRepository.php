<?php

namespace App\Repository;

use App\Entity\Depository;
use Doctrine\ORM\EntityRepository;

/**
 * @method Depository|null find($id, $lockMode = null, $lockVersion = null)
 * @method Depository|null findOneBy(array $criteria, array $orderBy = null)
 * @method Depository[]    findAll()
 * @method Depository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositoryRepository extends EntityRepository {

    public function getDepositoriesForSelect(?string $search) {
        $qb = $this->createQueryBuilder("depot");

        return $qb->select("depot.id AS id, depot.name AS text")
            ->where("depot.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }
}
