<?php

namespace App\Repository;

use App\Entity\WorkFreeDay;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method WorkFreeDay|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkFreeDay|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkFreeDay[]    findAll()
 * @method WorkFreeDay[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkFreeDayRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [["day", "asc"]];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function findForDatatable(array $params): array {
        $qb = $this->createQueryBuilder("work_free_day");

        $total = QueryHelper::count($qb, "work_free_day");

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("work_free_day.$column", $order["dir"]);
            }
        } else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("work_free_day.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "work_free_day");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function findDayAndMonth($day, $month){
        return $this->createQueryBuilder("work_free_day")
            ->select('work_free_day.day')
            ->addSelect('work_free_day.month')
            ->andWhere('work_free_day.day =:day')
            ->andWhere('work_free_day.month =:month')
            ->setParameters([
                'day' => $day,
                'month' => $month
            ])
            ->getQuery()
            ->getResult();
    }

}
