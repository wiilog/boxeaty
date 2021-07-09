<?php

namespace App\Repository;

use App\Entity\Status;
use Doctrine\ORM\EntityRepository;

/**
 * @method Status|null find($id, $lockMode = null, $lockVersion = null)
 * @method Status|null findOneBy(array $criteria, array $orderBy = null)
 * @method Status[]    findAll()
 * @method Status[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusRepository extends EntityRepository {

    function getForSelect(?string $search){
        return $this->createQueryBuilder("status")
            ->select("status.id AS id, status.name AS text")
            ->where("status.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }
}
