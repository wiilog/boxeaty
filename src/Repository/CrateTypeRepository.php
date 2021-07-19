<?php

namespace App\Repository;

use App\Entity\ClientBoxType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ClientBoxType|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientBoxType|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientBoxType[]    findAll()
 * @method ClientBoxType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CrateTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientBoxType::class);
    }

    // /**
    //  * @return ClientBoxType[] Returns an array of ClientBoxType objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ClientBoxType
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
