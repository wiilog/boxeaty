<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\Client;
use App\Entity\ClientOrder;
use App\Entity\Depository;
use App\Entity\Role;
use App\Entity\Status;
use App\Entity\User;
use App\Helper\QueryHelper;
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

    public const DEFAULT_DATATABLE_ORDER = [];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function findByType(string $type, DateTime $dateMin, DateTime $dateMax) {
        return $this->createQueryBuilder("client_order")
            ->select('client_order.number as number')
            ->addSelect('client_order.automatic as automatic')
            ->addSelect('client_order.number as clientOrderNumber')
            ->addSelect('client_order.deliveryPrice as deliveryPrice')
            ->addSelect('delivery.tokens as deliveryTokens')
            ->addSelect('deliveryRound.cost as deliveryCost')
            ->addSelect('lines.id as lineId')
            ->addSelect('lines.unitPrice as unitPrice')
            ->addSelect('lines.quantity as lineQuantity')
            ->addSelect('boxType.id as boxTypeId')
            ->addSelect('boxType.name as boxTypeName')
            ->addSelect('client.id as clientId')
            ->addSelect('client.paymentModes as paymentModes')
            ->addSelect('orderRecurrence.monthlyPrice as monthlyPrice')
            ->addSelect('orderRecurrence.crateAmount as crateAmount')
            ->addSelect('information.workingDayDeliveryRate as workingDayDeliveryRate')
            ->addSelect('information.nonWorkingDayDeliveryRate as nonWorkingDayDeliveryRate')
            ->leftJoin('client_order.client', 'client')
            ->leftJoin('client_order.deliveryRound', 'deliveryRound')
            ->leftJoin('client_order.delivery', 'delivery')
            ->leftJoin('client.clientOrderInformation', 'information')
            ->leftJoin('information.orderRecurrence', 'orderRecurrence')
            ->leftJoin('client_order.lines', 'lines')
            ->leftJoin('client_order.type', 'type')
            ->leftJoin('lines.boxType', 'boxType')
            ->andWhere('client_order.createdAt BETWEEN :dateMin AND :dateMax')
            ->andWhere("type.code = :typeCode")
            ->setParameters([
                "typeCode" => $type,
                'dateMin' => $dateMin,
                'dateMax' => $dateMax,
            ])
            ->getQuery()
            ->getResult();
    }

    public function createBetween(?User $user, DateTime $from, DateTime $to, array $params = []): QueryBuilder {
        $qb = $this->createQueryBuilder("client_order")
            ->andWhere("client_order.expectedDelivery BETWEEN :_from AND :_to")
            ->orderBy("client_order.expectedDelivery", "ASC")
            ->setParameter("_from", $from)
            ->setParameter("_to", $to);

        if($user && !$user->hasRight(Role::VIEW_ALL_ORDERS)) {
            $qb->andWhere("client_order.requester = :_requester")
                ->setParameter("_requester", $user);
        }

        if(isset($params["depository"])) {
            $qb->join("client_order.client", "_depository_client")
                ->join("_depository_client.clientOrderInformation", "_depository_client_information")
                ->andWhere("_depository_client_information.depository = :_depository")
                ->setParameter("_depository", $params["depository"]);
        }

        if(isset($params["deliverer"])) {
            $qb->leftJoin("client_order.deliveryRound", "_deliverer_delivery_round")
                ->andWhere("_deliverer_delivery_round.deliverer = :_deliverer")
                ->setParameter("_deliverer", $params["deliverer"]);
        }

        if(isset($params["client"])) {
            $qb->andWhere("client_order.client = :_client")
                ->setParameter("_client", $params["client"]);
        }

        return $qb;
    }

    /**
     * @return ClientOrder[]
     */
    public function findBetween(?User $user, DateTime $from, DateTime $to, array $params): array {
        return $this->createBetween($user, $from, $to, $params)
            ->join("client_order.status", "status")
            ->andWhere("status.code != :finished")
            ->setParameter("finished", Status::CODE_ORDER_FINISHED)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientOrder[]
     */
    public function findDeliveriesBetween(?User $user, DateTime $from, DateTime $to, array $params): array {
        return $this->createBetween($user, $from, $to, $params)
            ->leftJoin("client_order.status", "status")
            ->leftJoin("client_order.deliveryRound", "delivery_round")
            ->andWhere("status.code NOT IN (:statuses)")
            ->andWhere("delivery_round.id IS NULL")
            ->setParameter("statuses", [Status::CODE_ORDER_TO_VALIDATE_CLIENT, Status::CODE_ORDER_TO_VALIDATE_BOXEATY])
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ClientOrder[]
     */
    public function findLaunchableOrders(?User $user, Depository $depository, DateTime $from = null, DateTime $to = null): array {
        if($from == null && $to == null) {
            $returnOrderBetween = $this->createQueryBuilder('client_order');
        } else {
            $returnOrderBetween = $this->createBetween($user, $from, $to);
        }

        return $returnOrderBetween
            ->leftJoin("client_order.preparation", "preparation")
            ->leftJoin("client_order.client", "client")
            ->leftJoin("client_order.status", "status")
            ->leftJoin("client.clientOrderInformation", "client_order_information")
            ->andWhere("client_order_information.depository = :depository ")
            ->andWhere("preparation.id IS NULL")
            ->andWhere("status.code NOT IN (:statusCodes) ")
            ->setParameter("depository", $depository)
            ->setParameter("statusCodes", [Status::CODE_ORDER_TO_VALIDATE_BOXEATY, Status::CODE_ORDER_TO_VALIDATE_CLIENT])
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params, ?User $user = null): array {
        $qb = $this->createQueryBuilder("clientOrder");

        if($user && !$user->hasRight(Role::VIEW_ALL_ORDERS)) {
            if($user->getRole()->isAllowEditOwnGroupOnly()) {
                $qb->join("clientOrder.client", "client")
                    ->andWhere("client.group IN (:groups)")
                    ->andWhere("client IN (:clients)")
                    ->setParameter("groups", $user->getGroups())
                    ->setParameter("clients", $user->getClients());
            }

            $qb->andWhere("clientOrder.requester = :permission_user")
                ->setParameter("permission_user", $user);
        }

        $total = QueryHelper::count($qb, "clientOrder");

        foreach($params["filters"] ?? [] as $name => $value) {
            switch($name) {
                case "from":
                    $qb->andWhere("clientOrder.expectedDelivery >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("clientOrder.expectedDelivery <= :to")
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

    public function findLatestDraft(User $user) {
        return $this->createQueryBuilder("client_order")
            ->join("client_order.status","status")
            ->andWhere("status.code = :draft")
            ->andWhere("client_order.requester = :requester")
            ->orderBy("client_order.createdAt", "DESC")
            ->setMaxResults(1)
            ->setParameter("draft", Status::CODE_ORDER_TO_VALIDATE_CLIENT)
            ->setParameter("requester", $user)
            ->getQuery()
            ->getOneOrNullResult();
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
            ->andWhere('status.code != :finished')
            ->setParameter(':crateOrBox', $crateOrBox)
            ->setParameter(':finished', Status::CODE_ORDER_FINISHED);

        $res = $queryBuilder
            ->getQuery()
            ->getResult();

        return $res[0] ?? null;
    }

    public function getLastNumberByDate(string $prefix, string $date): ?string {
        $result = $this->createQueryBuilder('clientOrder')
            ->select('clientOrder.number')
            ->andWhere('clientOrder.number LIKE :value')
            ->orderBy('clientOrder.createdAt', 'DESC')
            ->addOrderBy('clientOrder.number', 'DESC')
            ->setMaxResults(1)
            ->setParameter('value', "$prefix$date%")
            ->getQuery()
            ->execute();

        return $result ? $result[0]['number'] : null;
    }

    public function findQuantityDeliveredBetweenDateAndClient(DateTime $from,
                                                              DateTime $to,
                                                              Client   $client): int {
        $result = $this->createQueryBuilder("client_order")
            ->select('SUM(lines.quantity)')
            ->leftJoin("client_order.delivery", "delivery")
            ->leftJoin('client_order.lines', 'lines')
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
