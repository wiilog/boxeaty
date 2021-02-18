<?php

namespace App\Repository;

use App\Entity\Client;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("client")
            ->select("client.name AS name")
            ->addSelect("client.active AS active")
            ->addSelect("client.address AS address")
            ->addSelect("user.username AS username")
            ->leftJoin("client.user", "user")
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("client");
        $total = QueryCounter::count($qb, "client");

        if ($search) {
            $qb
                ->where("client.name LIKE :search")
                ->orWhere("client.address LIKE :search")
                ->orWhere("client.address LIKE :search")
                ->orWhere("search_user.username LIKE :search")
                ->leftJoin("client.user", "search_user")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "assignedUser") {
                $qb->join("client.user", "order_user")
                    ->addOrderBy("order_user.username", $order["dir"]);
            } else {
                $qb->addOrderBy("client.$column", $order["dir"]);
            }
        }

        $filtered = QueryCounter::count($qb, "client");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }
}
