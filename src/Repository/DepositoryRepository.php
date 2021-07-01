<?php

namespace App\Repository;

use App\Entity\Depository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Depository|null find($id, $lockMode = null, $lockVersion = null)
 * @method Depository|null findOneBy(array $criteria, array $orderBy = null)
 * @method Depository[]    findAll()
 * @method Depository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depository::class);
    }

    // /**
    //  * @return Depository[] Returns an array of Depository objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Depository
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
