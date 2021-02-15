<?php

namespace App\Repository;

use App\Entity\Location;
use App\Helper\QueryCounter;
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
        $total = QueryCounter::count($qb, "location");

        if ($search) {
            $qb->where("location.name LIKE :search")
                ->orWhere("location.description LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("location.$column", $order["dir"]);
        }

        $filtered = QueryCounter::count($qb, "location");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }
}
