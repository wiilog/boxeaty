<?php

namespace App\Repository;

use App\Entity\Group;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("g")
            ->select("g.name AS name")
            ->addSelect("g.establishment AS establishment")
            ->addSelect("g.active AS active")
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("g");
        $total = QueryHelper::count($qb, "g");

        if ($search) {
            $qb->where("g.name LIKE :search")
                ->orWhere("g.establishment LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("g.$column", $order["dir"]);
        }

        $filtered = QueryHelper::count($qb, "g");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("g")
            ->select("g.id AS id, g.name AS text")
            ->where("g.name LIKE :search")
            ->andWhere("g.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
