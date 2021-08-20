<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Depository;
use App\Entity\Status;
use DateTime;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

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

        if(isset($params["client"])) {
            $qb ->andWhere("client_order.client = :client")
                ->setParameter("client", $params["client"]);
        }

        return $qb;
    }

    /**
     * @return ClientOrder[]
     */
    public function findBetween(DateTime $from, DateTime $to, array $params): array {
        return $this->createBetween($from, $to, $params)
            ->leftJoin("client_order.status", "status")
            ->andWhere("status.code IN (:statuses)")
            ->setParameter("statuses", [Status::CODE_ORDER_TO_VALIDATE_BOXEATY, Status::CODE_ORDER_PLANNED, Status::CODE_ORDER_TRANSIT])
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
            ->setParameter("statuses", [Status::CODE_DELIVERY_PLANNED, Status::CODE_DELIVERY_PREPARING])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientOrder[]
     */
    public function findOrders(array $params, Depository $depository, DateTime $from = null, DateTime $to = null): array {
        if($from == null && $to == null){
            $returnOrderBetween = $this->createQueryBuilder('client_order');
        } else {
            $returnOrderBetween = $this->createBetween($from, $to, $params);
        }
        return $returnOrderBetween
            ->leftJoin("client_order.status", "client_order_status")
            ->leftJoin("client_order.client", "client_order_client")
            ->where("client_order_client.depository = :depository ")
            ->andWhere("client_order_status.code IN (:status)")
            ->setParameter("status", [Status::CODE_ORDER_PLANNED])
            ->setParameter("depository", $depository)
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

        $total = QueryHelper::count($qb, "clientOrder");

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

        $filtered = QueryHelper::count($qb, "clientOrder");

        $qb->orderBy('clientOrder.expectedDelivery', 'ASC')
            ->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function findLastInProgressFor(Box $crateOrBox): ?ClientOrder {
        $queryBuilder = $this->createQueryBuilder('clientOrder');

        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->join('clientOrder.preparation', 'preparation')
            ->join('preparation.lines', 'line')
            ->leftJoin('line.crate', 'crate')
            ->leftJoin('line.boxes', 'box')
            ->join('clientOrder.status', 'status')
            ->andWhere($exprBuilder->orX(
                'crate = :crateOrBox',
                'box = :crateOrBox'
            ))
            ->andWhere('status.code IN (:inProgressStatuses)')
            ->setParameter(':crateOrBox', $crateOrBox)
            ->setParameter(':inProgressStatuses', [Status::CODE_ORDER_PLANNED, Status::CODE_ORDER_TO_VALIDATE_BOXEATY, Status::CODE_ORDER_TRANSIT]);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return $res[0] ?? null;
    }

    public function getLastNumberByDate(string $date): ?string {
        $result = $this->createQueryBuilder('clientOrder')
            ->select('clientOrder.number')
            ->where('clientOrder.number LIKE :value')
            ->orderBy('clientOrder.createdAt', 'DESC')
            ->addOrderBy('clientOrder.number', 'DESC')
            ->setParameter('value', ClientOrder::PREFIX_NUMBER . $date . '%')
            ->getQuery()
            ->execute();
        return $result ? $result[0]['number'] : null;
    }

    public function findQuantityDeliveredBetweenDateAndClient(DateTime $from,
                                                              DateTime $to,
                                                              Client $client): int {
        $result = $this->createQueryBuilder("client_order")
            ->select('SUM(lines.quantity)')
            ->leftJoin("client_order.delivery", "delivery")
            ->leftJoin('client_order.lines','lines')
            ->andWhere("delivery.deliveredAt BETWEEN :from AND :to")
            ->andWhere("client_order.client = :client")
            ->setParameter("client", $client)
            ->setParameter("from", $from)
            ->setParameter("to", $to)
            ->getQuery()
            ->getSingleScalarResult();
        return $result ? intval($result) : 0;
    }

}
