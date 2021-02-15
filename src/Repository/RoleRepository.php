<?php

namespace App\Repository;

use App\Entity\Role;
use App\Entity\User;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends EntityRepository {

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("role");
        $total = QueryCounter::count($qb, "role");

        if ($search) {
            $qb->where("role.label LIKE :search")
                ->orWhere("role.code LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("role.$column", $order["dir"]);
        }

        $filtered = QueryCounter::count($qb, "role");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getDeletable(array $roles): array {
        $uses = $this->createQueryBuilder("role")
            ->select("role.id AS id, COUNT(user) AS uses")
            ->leftJoin(User::class, "user", Join::WITH, "user.role = role.id")
            ->where("role.id IN (:roles)")
            ->groupBy("role")
            ->setParameter("roles", $roles)
            ->getQuery()
            ->getResult();

        $deletable = [];
        foreach($uses as $use) {
            $deletable[$use["id"]] = $use["uses"] === 0;
        }

        return $deletable;
    }

}
