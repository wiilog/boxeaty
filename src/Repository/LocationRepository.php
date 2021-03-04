<?php

namespace App\Repository;

use App\Entity\Location;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Location|null find($id, $lockMode = null, $lockVersion = null)
 * @method Location|null findOneBy(array $criteria, array $orderBy = null)
 * @method Location[]    findAll()
 * @method Location[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LocationRepository extends EntityRepository {

    public function iterateAllLocations() {
        return $this->createQueryBuilder("location")
            ->select("location.name AS name")
            ->addSelect("location.active AS active")
            ->andWhere("location.kiosk = 0")
            ->getQuery()
            ->toIterable();
    }

    public function findLocationsForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("location")
            ->where("location.kiosk = 0");

        $total = QueryHelper::count($qb, "location");

        if ($search) {
            $qb->andWhere("location.name LIKE :search OR location.description LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("location.$column", $order["dir"]);
        }

        $filtered = QueryHelper::count($qb, "location");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getLocationsForSelect(?string $search) {
        return $this->createQueryBuilder("location")
            ->select("location.id AS id, location.name AS text")
            ->where("location.kiosk = 0")
            ->andWhere("location.name LIKE :search")
            ->andWhere("location.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function iterateAllKiosks() {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.name AS name")
            ->addSelect("kiosk.active AS active")
            ->addSelect("join_client.name AS client")
            ->join("kiosk.client", "join_client")
            ->where("kiosk.kiosk = 1")
            ->getQuery()
            ->toIterable();
    }

    public function findKiosksForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("kiosk")
            ->where("kiosk.kiosk = 1");

        $total = QueryHelper::count($qb, "kiosk");

        if ($search) {
            $qb->where("kiosk.name LIKE :search OR search_client.name LIKE :search")
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

    public function getKiosksForSelect(?string $search) {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.id AS id, kiosk.name AS text")
            ->where("kiosk.kiosk = 1")
            ->andWhere("kiosk.name LIKE :search")
            ->andWhere("kiosk.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getAnyForSelect(?string $search) {
        return $this->createQueryBuilder("kiosk")
            ->select("kiosk.id AS id, kiosk.name AS text")
            ->where("kiosk.name LIKE :search")
            ->andWhere("kiosk.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getTotalDeposits(): int {
        return $this->createQueryBuilder("kiosk")
            ->select("SUM(kiosk.deposits)")
            ->where("kiosk.kiosk = 1")
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDeliverer(): Location {
        return $this->createQueryBuilder("location")
            ->where("location.code = '" . Location::DELIVERER . "'")
            ->getQuery()
            ->getSingleResult();
    }

}
