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
class DeliveryRepository extends EntityRepository {

    public function getTotalQuantityByClientAndDeliveredDate($client, $dateMax, $dateMin = null) {
        $qb = $this->createQueryBuilder('delivery')
            ->select('sum(lines.quantity)')
            ->andWhere($dateMin
                ? 'delivery.deliveredAt BETWEEN :dateMin AND :dateMax'
                : 'delivery.deliveredAt <= :dateMax')
            ->andWhere('clientOrder.client = :client')
            ->join('delivery.order', 'clientOrder')
            ->join('clientOrder.lines', 'lines')
            ->setParameters([
                'dateMax' => $dateMax,
                'client' => $client,
            ]);

        if ($dateMin) {
            $qb->setParameter('dateMin', $dateMin);
        }

        $result = $qb
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? intval($result) : 0;
    }

}
