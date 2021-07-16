<?php

namespace App\Repository;

use App\Entity\Depository;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Depository|null find($id, $lockMode = null, $lockVersion = null)
 * @method Depository|null findOneBy(array $criteria, array $orderBy = null)
 * @method Depository[]    findAll()
 * @method Depository[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DepositoryRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function getForSelect(?string $search) {
        $qb = $this->createQueryBuilder("depository");

        return $qb->select("depository.id AS id, depository.name AS text")
            ->andWhere("depository.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("depository");
        $total = QueryHelper::count($qb, "depository");

        if ($search) {
            $qb->where($qb->expr()->orX(
                "depository.name LIKE :search",
                "depository.description LIKE :search",
            ))->setParameter("search", "%$search%");
        }


        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("depository.$column", $order["dir"]);
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("depository.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "depository");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function iterateAll() {
        return $this->createQueryBuilder("depository")
            ->select("depository.name AS name")
            ->addSelect("IF(depository.active = 1, 'Actif', 'Inactif') AS active")
            ->addSelect("depository.description AS description")
            ->getQuery()
            ->toIterable();
    }

    public function getAll() {
        return $this->createQueryBuilder("depository")
            ->getQuery()
            ->getArrayResult();
    }

}
