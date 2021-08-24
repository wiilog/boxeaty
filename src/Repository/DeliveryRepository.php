<?php

namespace App\Repository;

use App\Entity\Delivery;
use Doctrine\ORM\EntityRepository;

/**
 * @method Delivery|null find($id, $lockMode = null, $lockVersion = null)
 * @method Delivery|null findOneBy(array $criteria, array $orderBy = null)
 * @method Delivery[]    findAll()
 * @method Delivery[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRepository extends EntityRepository
{
    public function getTotalQuantityByClientAndDeliveredDate($client, $dateMin, $dateMax)
    {
        $qb = $this->createQueryBuilder('delivery')
            ->select('sum(lines.quantity)')
            ->andWhere('delivery.deliveredAt BETWEEN :dateMin AND :dateMax')
            ->andWhere('clientOrder.client =:client')
            ->join('delivery.order', 'clientOrder')
            ->join('clientOrder.lines', 'lines')
            ->setParameters([
                'dateMin' => $dateMin,
                'dateMax' => $dateMax,
                'client' => $client,
            ]);
        $result = $qb
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? intval($result) : 0;
    }

    public function getDeliveredTokenByClientOrder($clientOrder)
    {
        $qb = $this->createQueryBuilder('delivery')
            ->select('delivery.tokens')
            ->where('delivery.order =:clientOrder')
            ->setParameters([
                'clientOrder' => $clientOrder,
            ]);
        $result = $qb
            ->getQuery()
            ->getResult();
        return $result ? intval($result) : 0;
    }

}
