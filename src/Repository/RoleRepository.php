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

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("role")
            ->select("role.name AS name")
            ->addSelect("role.active AS active")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("role")
            ->andWhere("role.code NOT LIKE :no_access")
            ->setParameter('no_access', Role::ROLE_NO_ACCESS);;
        $total = QueryHelper::count($qb, "role");

        if ($search) {
            $qb->andWhere("role.name LIKE :search")
                ->setParameter("search", "%$search%");
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("role.$column", $order["dir"]);
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("role.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "role");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
