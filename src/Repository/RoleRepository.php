<?php

namespace App\Repository;

use App\Entity\Role;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoleRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("role")
            ->select("role.name AS name")
            ->addSelect("role.active AS active")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("role");
        $total = QueryHelper::count($qb, "role");
        $qb
            ->andWhere("role.code NOT LIKE :no_access")
            ->setParameter('no_access', Role::ROLE_NO_ACCESS);
        if ($search) {
            $qb->andWhere("role.name LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("role.$column", $order["dir"]);
        }

        $filtered = QueryHelper::count($qb, "role");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
