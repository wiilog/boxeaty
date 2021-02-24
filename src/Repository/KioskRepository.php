<?php

namespace App\Repository;

use App\Entity\Kiosk;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Kiosk|null find($id, $lockMode = null, $lockVersion = null)
 * @method Kiosk|null findOneBy(array $criteria, array $orderBy = null)
 * @method Kiosk[]    findAll()
 * @method Kiosk[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KioskRepository extends EntityRepository {

    public function getAll() {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.id AS id")
            ->addSelect("kiosk.name AS name")
            ->addSelect("client_entity.id AS client")
            ->join("kiosk.client", "client_entity")
            ->getQuery()
            ->getArrayResult();
    }

    public function iterateAll() {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.name AS name")
            ->addSelect("join_client.name AS client")
            ->join("kiosk.client", "join_client")
            ->getQuery()
            ->iterate();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("kiosk");
        $total = QueryHelper::count($qb, "kiosk");

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

        $filtered = QueryHelper::count($qb, "kiosk");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.id AS id, kiosk.name AS text")
            ->where("kiosk.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
