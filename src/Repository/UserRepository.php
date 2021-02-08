<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository {

    public function findByLogin(string $login) {
        return $this->createQueryBuilder("user")
            ->where("user.username LIKE :login")
            ->orWhere("user.email LIKE :login")
            ->setParameter("login", $login)
            ->getQuery()
            ->getResult();
    }

}
