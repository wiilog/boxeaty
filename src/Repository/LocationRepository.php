<?php

namespace App\Repository;

use App\Entity\Location;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Location|null find($id, $lockMode = null, $lockVersion = null)
 * @method Location|null findOneBy(array $criteria, array $orderBy = null)
 * @method Location[]    findAll()
 * @method Location[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LocationRepository extends EntityRepository {

    public function iterateAll()
    {
        return $this->createQueryBuilder("location")
            ->select("IF(location.kiosk = 0, 'Emplacement', 'Borne') AS type")
            ->addSelect("location.name AS name")
            ->addSelect("client.name AS client_name")
            ->addSelect("location.active AS active")
            ->addSelect("location.description AS description")
            ->leftJoin("location.client", "client")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("location");
        QueryHelper::withCurrentGroup($qb, "location.client.group", $user);

        $total = QueryHelper::count($qb, "location");

        if ($search) {
            $qb->andWhere("location.name LIKE :search OR location.description LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "client_name") {
                $qb->leftJoin("location.client", "location_client")
                    ->addOrderBy("location_client.name", $order["dir"]);
            } else {
                $qb->addOrderBy("location.$column", $order["dir"]);
            }
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

}
