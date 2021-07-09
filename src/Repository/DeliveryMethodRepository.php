<?php

namespace App\Repository;

use App\Entity\DeliveryMethod;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method DeliveryMethod|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeliveryMethod|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeliveryMethod[]    findAll()
 * @method DeliveryMethod[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryMethodRepository extends EntityRepository
{

    public const DEFAULT_DATATABLE_ORDER = [["name", "asc"]];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("delivery_method")
            ->select("delivery_method.id AS id, delivery_method.name AS text")
            ->where("delivery_method.name LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function findForDatatable(array $params): array
    {
        $qb = $this->createQueryBuilder("dm")
            ->andWhere("dm.deleted = 0");

        $total = QueryHelper::count($qb, "dm");

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("dm.$column", $order["dir"]);
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("dm.$column", $dir);
            }
        }
        $filtered = QueryHelper::count($qb, "dm");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }
}
