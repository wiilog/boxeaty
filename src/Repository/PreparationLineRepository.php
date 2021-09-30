<?php

namespace App\Repository;

use App\Entity\PreparationLine;
use App\Entity\Status;
use DateTime;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

/**
 * @method PreparationLine|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreparationLine|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreparationLine[]    findAll()
 * @method PreparationLine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationLineRepository extends EntityRepository {

    public function countDeliveredByType(DateTime $from, DateTime $to): array {
        $res = $this->createQueryBuilder('line')
            ->select('COUNT(line.id) AS count')
            ->addSelect('join_type.id AS type')
            ->join('line.boxes', 'join_boxes')
            ->join('join_boxes.type', 'join_type')
            ->join('line.preparation', 'join_preparation')
            ->join('join_preparation.order', 'join_order')
            ->join('join_order.delivery', 'join_delivery')
            ->join('join_delivery.status', 'join_status')
            ->andWhere('join_delivery.deliveredAt BETWEEN :from AND :to')
            ->andWhere('join_status.code = :delivered_status')
            ->groupBy('join_type.id')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('delivered_status', Status::CODE_DELIVERY_DELIVERED)
            ->getQuery()
            ->getResult();

        return Stream::from($res)
            ->keymap(fn(array $line) => [$line['type'], $line['count']])
            ->toArray();
    }

}
