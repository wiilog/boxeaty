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
class LocationRepository extends EntityRepository
{
    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("location");
        $total = QueryHelper::count($qb, "location");

        if ($search) {
            $qb->andWhere("location.name LIKE :search OR location.description LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach($params["filters"] as $name => $value) {
            $qb->andWhere("location.$name LIKE :filter_$name")
                ->setParameter("filter_$name", "%$value%");
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

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("location")
            ->select("location.id AS id, location.name AS text")
            ->where("location.name LIKE :search")
            ->andWhere("location.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }
}
