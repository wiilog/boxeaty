<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\DeliveryRound;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use DateTime;

/**
 * @method DeliveryRound|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRound|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryRound[]    findAll()
 * @method DeliveryRound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRoundRepository extends EntityRepository {

    public function getLastNumberByDate(string $prefix, string $date): ?string {
        $result = $this->createQueryBuilder("delivery_round")
            ->select("delivery_round.number")
            ->andWhere("delivery_round.number LIKE :value")
            ->orderBy("delivery_round.id", "DESC")
            ->addOrderBy("delivery_round.number", "DESC")
            ->setMaxResults(1)
            ->setParameter("value", "$prefix$date%")
            ->getQuery()
            ->execute();

        return $result ? $result[0]["number"] : null;
    }

    public function findAwaitingDeliverer(User $deliverer) {
        return $this->createQueryBuilder("delivery_round")
            ->join("delivery_round.status", "status")
            ->join("delivery_round.orders", "orders")
            ->andWhere("status.code IN (:status)")
            ->andWhere("orders.id IS NOT NULL")
            ->andWhere("delivery_round.deliverer = :deliverer")
            ->setParameter("status", [Status::CODE_ROUND_CREATED, Status::CODE_ROUND_AWAITING_DELIVERER])
            ->setParameter("deliverer", $deliverer)
            ->getQuery()
            ->getResult();
    }

    public function findDeliveryTotalDistance(
        DateTime $from,
        DateTime $to,
        Client   $client,
        array    $deliveryMethod)
    {
        return $this->createQueryBuilder("delivery_round")
            ->select('delivery_round.distance')
            ->leftJoin("delivery_round.orders", "client_orders")
            ->leftJoin("client_orders.client", "client")
            ->leftJoin("client_orders.delivery", "delivery")
            ->leftJoin("delivery_round.deliveryMethod", "delivery_method")
            ->andWhere("delivery.deliveredAt BETWEEN :from AND :to")
            ->andWhere("client_orders.client = :client")
            ->andWhere("delivery_method.type IN (:type)")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->setParameter("client", $client)
            ->setParameter("type", $deliveryMethod)
            ->groupBy("delivery_round.id")
            ->getQuery()
            ->getResult();
    }
}
