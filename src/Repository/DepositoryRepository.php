<?php

namespace App\Repository;

use App\Entity\Depository;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @method Depository|null find($id, $lockMode = null, $lockVersion = null)
 * @method Depository|null findOneBy(array $criteria, array $orderBy = null)
 * @method Depository[]    findAll()
 * @method Depository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositoryRepository extends EntityRepository {

    public function getForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("depository");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("depository.client", "client")
                ->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("depository.id AS id, depository.name AS text")
            ->where("depository.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
