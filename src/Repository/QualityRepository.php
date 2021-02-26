<?php

namespace App\Repository;

use App\Entity\Quality;
use App\Entity\TrackingMovement;
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
            ->getQuery()
            ->iterate();
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("quality")
            ->select("quality.id AS id, quality.name AS text")
            ->where("quality.name LIKE :search")
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

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getDeletable(array $qualities): array {
        $uses = $this->createQueryBuilder("quality")
            ->select("quality.id AS id, (COUNT(movement) + 0) AS uses") //TODO: replace 0 by COUNT(box)
            ->leftJoin(TrackingMovement::class, "movement", Join::WITH, "movement.quality = quality.id")
            ->addSelect("0 AS box")//TODO: replace this line by a left join on boxes
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
