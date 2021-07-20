<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\Location;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Location|null find($id, $lockMode = null, $lockVersion = null)
 * @method Location|null findOneBy(array $criteria, array $orderBy = null)
 * @method Location[]    findAll()
 * @method Location[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LocationRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("location")
            ->select("IF(location.kiosk = 0, 'Emplacement', 'Borne') AS type")
            ->addSelect("location.name AS name")
            ->addSelect("join_depository.name AS depository")
            ->addSelect("join_client.name AS client_name")
            ->addSelect("location.active AS active")
            ->addSelect("location.description AS description")
            ->addSelect("COUNT(box) AS boxes")
            ->addSelect("location.capacity AS capacity")
            ->addSelect("location.type AS locationType")
            ->leftJoin("location.client", "join_client")
            ->leftJoin("location.depository", "join_depository")
            ->leftJoin(Box::class, "box", Join::WITH, "box.location = location.id")
            ->groupBy("location")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("location");
        QueryHelper::withCurrentGroup($qb, "location.client.group", $user);

        $total = QueryHelper::count($qb, "location");

        if ($search) {
            $qb->leftJoin("location.client", "client")
                ->andWhere($qb->expr()->orX(
                    "location.name LIKE :search",
                    "location.description LIKE :search",
                    "location.capacity = :exact_search",
                    "client.name LIKE :search",
                ))
                ->setParameter("search", "%$search%")
                ->setParameter("exact_search", $search);
        }

        foreach ($params["filters"] ?? [] as $name => $value) {
            switch ($name) {
                case "depository":
                    $qb->andWhere("location.depository = :raw_value")
                        ->setParameter("raw_value", $value);
                    break;
            }
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "client_name") {
                    $qb->leftJoin("location.client", "location_client")
                        ->addOrderBy("location_client.name", $order["dir"]);
                } if ($column === "container_amount") {
                    $qb
                        ->leftJoin('location.boxes', 'box')
                        ->groupBy('location')
                        ->addOrderBy("COUNT(box)", $order["dir"]);
                }else {
                    $qb->addOrderBy("location.$column", $order["dir"]);
                }
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("location.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "location");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getLocationsForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("location");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("location.client", "client")
                ->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("location.id AS id, location.name AS text")
            ->where("location.kiosk = 0")
            ->andWhere("location.name LIKE :search")
            ->andWhere("location.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getKiosksForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("kiosk");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("kiosk.client", "client")
                ->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("kiosk.id AS id, kiosk.name AS text")
            ->where("kiosk.kiosk = 1")
            ->andWhere("kiosk.name LIKE :search")
            ->andWhere("kiosk.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getAnyForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("location");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("location.client", "client")
                ->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("location.id AS id, location.name AS text")
            ->andWhere("location.name LIKE :search")
            ->andWhere("location.active = 1")
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

}
