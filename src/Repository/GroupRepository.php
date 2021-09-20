<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("g")
            ->select("g.name AS name")
            ->addSelect("g.active AS active")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user = null): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("g");
        QueryHelper::withCurrentGroup($qb, "g", $user);

        $total = QueryHelper::count($qb, "g");

        if ($search) {
            $qb->andWhere("g.name LIKE :search")
                ->setParameter("search", "%$search%");
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("g.$column", $order["dir"]);
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("g.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "g");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("g");
        QueryHelper::withCurrentGroup($qb, "g", $user);

        return $qb->select("g.id AS id, g.name AS text")
            ->andWhere("g.name LIKE :search")
            ->andWhere("g.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
