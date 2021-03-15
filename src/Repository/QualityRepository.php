<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\Quality;
use App\Entity\BoxRecord;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * @method Quality|null find($id, $lockMode = null, $lockVersion = null)
 * @method Quality|null findOneBy(array $criteria, array $orderBy = null)
 * @method Quality[]    findAll()
 * @method Quality[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QualityRepository extends EntityRepository
{
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

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("quality.$column", $order["dir"]);
        }

        $filtered = QueryHelper::count($qb, "quality");

        $qb->setFirstResult($params["start"] ?? 0)
            ->setMaxResults($params["length"] ?? 10);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getDeletable(array $qualities): array {
        $uses = $this->createQueryBuilder("quality")
            ->select("quality.id AS id, (COUNT(movement) + COUNT(box)) AS uses")
            ->leftJoin(BoxRecord::class, "movement", Join::WITH, "movement.quality = quality.id")
            ->leftJoin(Box::class, "box", Join::WITH, "box.quality = quality.id")
            ->where("quality.id IN (:qualities)")
            ->groupBy("quality")
            ->setParameter("qualities", $qualities)
            ->getQuery()
            ->getResult();

        $deletable = [];
        foreach($uses as $use) {
            $deletable[$use["id"]] = $use["uses"] === 0;
        }

        return $deletable;
    }

}
