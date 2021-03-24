<?php

namespace App\Repository;

use App\Entity\DepositTicket;
use App\Entity\User;
use App\Helper\QueryHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;

/**
 * @method DepositTicket|null find($id, $lockMode = null, $lockVersion = null)
 * @method DepositTicket|null findOneBy(array $criteria, array $orderBy = null)
 * @method DepositTicket[]    findAll()
 * @method DepositTicket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositTicketRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['creationDate', 'desc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("deposit_ticket")
            ->select("deposit_ticket.creationDate AS creation_date")
            ->addSelect("join_kiosk.name AS kiosk")
            ->addSelect("deposit_ticket.validityDate AS validity_date")
            ->addSelect("deposit_ticket.number AS number")
            ->addSelect("deposit_ticket.useDate AS use_date")
            ->addSelect("join_box_type.price AS depositAmount")
            ->addSelect("join_client.name AS client")
            ->addSelect("join_orderUser.username AS orderUser")
            ->addSelect("deposit_ticket.state AS state")
            ->leftJoin("deposit_ticket.location", "join_kiosk")
            ->leftJoin("deposit_ticket.box", "join_box")
            ->leftJoin("join_box.type", "join_box_type")
            ->leftJoin("deposit_ticket.orderUser", "join_orderUser")
            ->leftJoin("join_kiosk.client", "join_client")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("deposit_ticket");

        if ($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("deposit_ticket.location", "current_group_location")
                ->join("current_group_location.client", "current_group_client")
                ->andWhere("current_group_client.group IN (:current_groups)")
                ->setParameter("current_groups", $user->getGroups());
        }

        $total = QueryHelper::count($qb, "deposit_ticket");

        if ($search) {
            $qb
                ->leftJoin("deposit_ticket.location", "search_kiosk")
                ->leftJoin("search_kiosk.client", "search_client")
                ->leftJoin("deposit_ticket.box", "search_box")
                ->leftJoin("search_box.type", "search_box_type")
                ->leftJoin("deposit_ticket.orderUser", "search_order_user")
                ->andWhere($qb->expr()->orX(
                    "search_kiosk.name LIKE :search",
                    "deposit_ticket.number LIKE :search",
                    "search_client.name LIKE :search",
                    "deposit_ticket.state LIKE :search",
                    "REPLACE(search_box_type.price, '.', ',') LIKE :search",
                    "search_box_type.price LIKE :search",
                    "search_order_user.username LIKE :search",
                ))
                ->setParameter("search", "%$search%");
        }

        foreach ($params["filters"] ?? [] as $name => $value) {
            switch ($name) {
                case "from":
                    $qb->andWhere("DATE(deposit_ticket.creationDate) >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("DATE(deposit_ticket.creationDate) <= :to")
                        ->setParameter("to", $value);
                    break;
                case "kiosk":
                    $qb->andWhere("deposit_ticket.location = :filter_kiosk")
                        ->setParameter("filter_kiosk", $value);
                    break;
                case "state":
                    $qb->andWhere("deposit_ticket.state = :filter_state")
                        ->setParameter("filter_state", $value);
                    break;
                default:
                    $qb->andWhere("deposit_ticket.$name LIKE :filter_$name")
                        ->setParameter("filter_$name", "%$value%");
                    break;
            }
        }

        if (!empty($params['order'])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "kiosk") {
                    $qb->leftJoin("deposit_ticket.location", "order_location")
                        ->addOrderBy("order_location.name", $order["dir"]);
                } else if ($column === "client") {
                    $qb->leftJoin("deposit_ticket.location", "order_client_location")
                        ->leftJoin("order_client_location.client", "order_client")
                        ->addOrderBy("order_client.name", $order["dir"]);
                } else if ($column === 'orderUser') {
                    $qb
                        ->leftJoin('deposit_ticket.orderUser', 'order_orderUser')
                        ->addOrderBy('order_orderUser.username', $order["dir"]);
                } else if ($column === 'depositAmount') {
                    $qb
                        ->leftJoin('deposit_ticket.box', 'order_box')
                        ->leftJoin('order_box.type', 'order_box_type')
                        ->addOrderBy('order_box_type.price', $order["dir"]);
                } else if (property_exists(DepositTicket::class, $column)) {
                    $qb->addOrderBy("deposit_ticket.$column", $order["dir"]);
                }
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("deposit_ticket.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "deposit_ticket");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search, ?array $exclude, ?User $user = null) {
        $qb = $this->createQueryBuilder("deposit_ticket");

        if($exclude) {
            $qb->andWhere("deposit_ticket.number NOT IN (:excluded)")
                ->setParameter("excluded", $exclude);
        }

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("deposit_ticket.box", "same_group_box")
                ->join("same_group_box.owner", "owner")
                ->andWhere("owner.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("deposit_ticket.id AS id, deposit_ticket.number AS text, type.price AS price")
            ->join("deposit_ticket.box", "box")
            ->join("box.type", "type")
            ->andWhere("deposit_ticket.number LIKE :search")
            ->andWhere("deposit_ticket.validityDate > :now")
            ->andWhere("deposit_ticket.state = :valid")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->setParameter("now", new DateTime())
            ->setParameter("valid", DepositTicket::VALID)
            ->getQuery()
            ->getResult();
    }

}
