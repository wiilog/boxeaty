<?php

namespace App\Repository;

use App\Entity\OrderRecurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OrderRecurrence|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderRecurrence|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderRecurrence[]    findAll()
 * @method OrderRecurrence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRecurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderRecurrence::class);
    }

    // /**
    //  * @return OrderRecurrence[] Returns an array of OrderRecurrence objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OrderRecurrence
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
