<?php

namespace App\Repository;

use App\Entity\DepositTicket;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;
use function Doctrine\ORM\QueryBuilder;

/**
 * @method DepositTicket|null find($id, $lockMode = null, $lockVersion = null)
 * @method DepositTicket|null findOneBy(array $criteria, array $orderBy = null)
 * @method DepositTicket[]    findAll()
 * @method DepositTicket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositTicketRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("deposit_ticket")
            ->select("deposit_ticket.creationDate AS creation_date")
            ->addSelect("join_kiosk.name AS kiosk")
            ->addSelect("deposit_ticket.validityDate AS validity_date")
            ->addSelect("deposit_ticket.number AS number")
            ->addSelect("deposit_ticket.useDate AS use_date")
            ->addSelect("join_client.name AS client")
            ->addSelect("deposit_ticket.state AS state")
            ->join("deposit_ticket.location", "join_kiosk")
            ->join("join_kiosk.client", "join_client")
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
            $qb->join("deposit_ticket.location", "search_kiosk")
                ->join("search_kiosk.client", "search_client")
                ->where($qb->expr()->orX(
                    "search_kiosk.name LIKE :search",
                    "deposit_ticket.number LIKE :search",
                    "search_client.name LIKE :search",
                    "deposit_ticket.state LIKE :search",
                ))
                ->setParameter("search", "%$search%");
        }

        foreach ($params["filters"] as $name => $value) {
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

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "kiosk") {
                $qb->leftJoin("deposit_ticket.location", "order_location")
                    ->addOrderBy("order_location.name", $order["dir"]);
            } else if ($column === "client") {
                $qb->leftJoin("deposit_ticket.location", "order_client_location")
                    ->leftJoin("order_client_location.client", "order_client")
                    ->addOrderBy("order_client.name", $order["dir"]);
            } else {
                $qb->addOrderBy("deposit_ticket.$column", $order["dir"]);
            }
        }

        $filtered = QueryHelper::count($qb, "deposit_ticket");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
