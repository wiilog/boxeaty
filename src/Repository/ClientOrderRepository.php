<?php

namespace App\Repository;

use App\Entity\ClientOrder;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method ClientOrder|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClientOrder|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClientOrder[]    findAll()
 * @method ClientOrder[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientOrderRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    /**
     * @return ClientOrder[]
     */
    public function findBetween($from, $to): array {
        return $this->createQueryBuilder("client_order")
            ->where("client_order.expectedDelivery BETWEEN :from AND :to")
            ->orderBy("client_order.expectedDelivery", "ASC")
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params, User $user): array {

        $qb = $this->createQueryBuilder("clientOrder");
        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("clientOrder.client", "client")
                ->andWhere("client.group IN (:groups)")
                ->andWhere("client IN (:clients)")
                ->setParameter("groups", $user->getGroups())
                ->setParameter("clients", $user->getClients());
        }

        foreach ($params["filters"] ?? [] as $name => $value) {
            switch ($name) {
                case "from":
                    $qb->andWhere("DATE(clientOrder.expectedDelivery) >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("DATE(clientOrder.expectedDelivery) <= :to")
                        ->setParameter("to", $value);
                    break;
                case "client":
                    $qb->leftJoin("clientOrder.client", "filter_client")
                        ->andWhere("filter_client.id LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                case "status":
                    $qb->leftJoin("clientOrder.status", "filter_status")
                        ->andWhere("filter_status.id LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                case "type":
                    $qb->leftJoin("clientOrder.type", "filter_type")
                        ->andWhere("filter_type.id LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                default:
                    $qb->andWhere("clientOrder.$name = :filter_$name")
                        ->setParameter("filter_$name", $value);
                    break;
            }
        }

        $total = QueryHelper::count($qb, "clientOrder");
        $filtered = QueryHelper::count($qb, "clientOrder");
        $qb->orderBy('clientOrder.expectedDelivery', 'ASC');
        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
