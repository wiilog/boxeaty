<?php

namespace App\Repository;

use App\Entity\Quality;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Quality|null find($id, $lockMode = null, $lockVersion = null)
 * @method Quality|null findOneBy(array $criteria, array $orderBy = null)
 * @method Quality[]    findAll()
 * @method Quality[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QualityRepository extends EntityRepository
{
    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("quality")
            ->select("quality.name AS name")
            ->addSelect("quality.active AS active")
            ->getQuery()
            ->toIterable();
    }

    public function getForSelect(?string $search) {
        $qb = $this->createQueryBuilder("quality");

        if(!$search) {
            $qb->addOrderBy("quality.name", "ASC");

        }
        return $qb->select("quality.id AS id, quality.name AS text")
            ->where("quality.name LIKE :search")
            ->andWhere("quality.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("quality");
        $total = QueryHelper::count($qb, "quality");

        if ($search) {
            $qb->where("quality.name LIKE :search")
                ->setParameter("search", "%$search%");
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("quality.$column", $order["dir"]);
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("quality.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "quality");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
