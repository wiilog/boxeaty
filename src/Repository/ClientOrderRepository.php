<?php

namespace App\Repository;

use App\Entity\ClientOrder;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method ClientOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientOrder[]    findAll()
 * @method ClientOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientOrderRepository extends EntityRepository {

    /**
     * @return ClientOrder[]
     */
    public function findBetween(DateTime $from, DateTime $to, array $params): array {
        $qb = $this->createQueryBuilder("client_order")
            ->where("client_order.expectedDelivery BETWEEN :from AND :to")
            ->orderBy("client_order.expectedDelivery", "ASC")
            ->setParameter("from", $from)
            ->setParameter("to", $to);

        if(isset($params["depository"])) {
            $qb->leftJoin("client_order.deliveryRound", "depository_delivery_round")
                ->andWhere("depository_delivery_round.depository = :depository")
                ->setParameter("depository", $params["depository"]);
        }

        if(isset($params["deliverer"])) {
            $qb->leftJoin("client_order.deliveryRound", "deliverer_delivery_round")
                ->andWhere("deliverer_delivery_round.deliverer = :deliverer")
                ->setParameter("deliverer", $params["deliverer"]);
        }

        return $qb->getQuery()->getResult();
    }

}
