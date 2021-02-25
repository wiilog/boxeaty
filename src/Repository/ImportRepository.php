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

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "user") {
                QueryHelper::order($qb, "import.user.username", $order["dir"]);
            } else {
                $qb->addOrderBy("import.$column", $order["dir"]);
            }
        }

        $filtered = QueryHelper::count($qb, "import");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

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
