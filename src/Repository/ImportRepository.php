<?php

namespace App\Repository;

use App\Entity\Import;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Import|null find($id, $lockMode = null, $lockVersion = null)
 * @method Import|null findOneBy(array $criteria, array $orderBy = null)
 * @method Import[]    findAll()
 * @method Import[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImportRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['creationDate', 'desc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("import");
        $total = QueryHelper::count($qb, "import");

        if ($search) {
            $qb->where("import.name LIKE :search")
                ->orWhere("search_user.username LIKE :search")
                ->join("import.user", "search_user")
                ->setParameter("search", "%$search%");
        }

        foreach($params["filters"] ?? [] as $name => $value) {
            switch($name) {
                case "from":
                    $qb->andWhere("DATE(import.creationDate) >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("DATE(import.creationDate) <= :to")
                        ->setParameter("to", $value);
                    break;
                case "status":
                    $qb->andWhere("import.status = :filter_status")
                        ->setParameter("filter_status", $value);
                    break;
            }
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "user") {
                    QueryHelper::order($qb, "import.user.username", $order["dir"]);
                } else {
                    $qb->addOrderBy("import.$column", $order["dir"]);
                }
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("import.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "import");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function findUpcoming() {
        return $this->createQueryBuilder("import")
            ->where("import.status = " . Import::UPCOMING)
            ->getQuery()
            ->getResult();
    }

}
