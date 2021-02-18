<?php

namespace App\Repository;

use App\Entity\TrackingMovement;
use App\Helper\QueryCounter;
use Doctrine\ORM\EntityRepository;

/**
 * @method TrackingMovement|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrackingMovement|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrackingMovement[]    findAll()
 * @method TrackingMovement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrackingMovementRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("movement")
            ->select("movement.date AS date")
            ->addSelect("box.number AS box_number")
            ->addSelect("quality.name AS quality_name")
            ->addSelect("state.name AS state_name")
            ->addSelect("client.name AS client_name")
            ->addSelect("movement.comment AS comment")
            ->join("movement.box", "box")
            ->join("movement.quality", "quality")
            ->join("movement.state", "state")
            ->join("movement.client", "client")
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params) {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("movement");
        $total = QueryCounter::count($qb, "movement");

        if ($search) {
            //TODO: recherche rapide
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("movement.$column", $order["dir"]);
        }

        $filtered = QueryCounter::count($qb, "movement");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
