<?php

namespace App\Repository;

use App\Entity\DeliveryRound;
use App\Entity\Status;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/**
 * @method DeliveryRound|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryRound|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryRound[]    findAll()
 * @method DeliveryRound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRoundRepository extends EntityRepository {


    public function findAwaitingDeliverer(User $deliverer) {
        return $this->createQueryBuilder("delivery_round")
            ->join("delivery_round.status", "status")
            ->join("delivery_round.orders", "orders")
            ->where("status.code IN (:status)")
            ->andWhere("orders.id IS NOT NULL")
            ->andWhere("delivery_round.deliverer = :deliverer")
            ->setParameter("status", [Status::ROUND_CREATED, Status::ROUND_AWAITING_DELIVERER])
            ->setParameter("deliverer", $deliverer)
            ->getQuery()
            ->getResult();
    }


}
