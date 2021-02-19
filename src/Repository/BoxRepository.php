<?php

namespace App\Repository;

use App\Entity\Box;
use Doctrine\ORM\EntityRepository;

/**
 * @method Box|null find($id, $lockMode = null, $lockVersion = null)
 * @method Box|null findOneBy(array $criteria, array $orderBy = null)
 * @method Box[]    findAll()
 * @method Box[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxRepository extends EntityRepository {

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("box")
            ->select("box.id AS id, box.number AS text")
            ->where("box.number LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
