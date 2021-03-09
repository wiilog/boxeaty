<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends EntityRepository {

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("ord");
        QueryHelper::withCurrentGroup($qb, "ord.client.group", $user);

        $total = QueryHelper::count($qb, "ord");

        if ($search) {
            $qb->leftJoin("ord.location", "search_location")
                ->leftJoin("ord.client", "search_client")
                ->leftJoin("ord.boxes", "search_box")
                ->andWhere($qb->expr()->orX(
                    "search_box.number LIKE :search",
                    "search_location.name LIKE :search",
                    "search_client.name LIKE :search"
                ))
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "location") {
                $qb->leftJoin("ord.location", "order_location")
                    ->addOrderBy("order_location.name", $order["dir"]);
            } else if ($column === "user") {
                $qb->leftJoin("ord.user", "order_user")
                    ->addOrderBy("order_user.name", $order["dir"]);
            } else if ($column === "client") {
                $qb->leftJoin("ord.client", "order_client")
                    ->addOrderBy("order_client.name", $order["dir"]);
            } else {
                $qb->addOrderBy("ord.$column", $order["dir"]);
            }
        }

        $filtered = QueryHelper::count($qb, "ord");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
