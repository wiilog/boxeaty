<?php

namespace App\Repository;

use App\Entity\Kiosk;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method Kiosk|null find($id, $lockMode = null, $lockVersion = null)
 * @method Kiosk|null findOneBy(array $criteria, array $orderBy = null)
 * @method Kiosk[]    findAll()
 * @method Kiosk[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KioskRepository extends EntityRepository
{
    public function iterateAll() {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.name AS name")
            ->addSelect("join_client.name AS client")
            ->join("kiosk.client", "join_client")
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("kiosk");
        $total = QueryCounter::count($qb, "kiosk");

        if ($search) {
            $qb->where("kiosk.name LIKE :search")
                ->orWhere("search_client.name LIKE :search")
                ->join("kiosk.client", "search_client")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "client") {
                $qb->join("kiosk.client", "order_client")
                    ->addOrderBy("order_client.name", $order["dir"]);
            } else {
                $qb->addOrderBy("kiosk.$column", $order["dir"]);
            }
        }

        $filtered = QueryCounter::count($qb, "kiosk");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }
}
