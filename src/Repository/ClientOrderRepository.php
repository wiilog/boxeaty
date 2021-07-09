<?php

namespace App\Repository;

use App\Entity\ClientOrder;
use App\Entity\Status;
use DateTime;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @method ClientOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientOrder[]    findAll()
 * @method ClientOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientOrderRepository extends EntityRepository {

    public function createBetween(DateTime $from, DateTime $to, array $params): QueryBuilder {
        $qb = $this->createQueryBuilder("client_order")
            ->where("client_order.expectedDelivery BETWEEN :from AND :to")
            ->orderBy("client_order.expectedDelivery", "ASC")
            ->setParameter("from", $from)
            ->setParameter("to", $to);

        if(isset($params["depository"])) {
            $qb->leftJoin("client_order.deliveryRound", "_depository_delivery_round")
                ->andWhere("_depository_delivery_round.depository = :depository")
                ->setParameter("depository", $params["depository"]);
        }

        if(isset($params["deliverer"])) {
            $qb->leftJoin("client_order.deliveryRound", "_deliverer_delivery_round")
                ->andWhere("_deliverer_delivery_round.deliverer = :deliverer")
                ->setParameter("deliverer", $params["deliverer"]);
        }

        return $qb;
    }

    /**
     * @return ClientOrder[]
     */
    public function findBetween(DateTime $from, DateTime $to, array $params): array {
        return $this->createBetween($from, $to, $params)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientOrder[]
     */
    public function findDeliveriesBetween(DateTime $from, DateTime $to, array $params): array {
        return $this->createBetween($from, $to, $params)
            ->leftJoin("client_order.delivery", "delivery")
            ->leftJoin("delivery.status", "delivery_status")
            ->andWhere("delivery_status.code IN (:statuses)")
            ->setParameter("statuses", [Status::DELIVERY_PLANNED, Status::DELIVERY_PREPARING])
            ->getQuery()
            ->getResult();
    }

}
