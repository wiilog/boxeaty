<?php

namespace App\Repository;

use App\Entity\TrackingMovement;
use App\Helper\QueryHelper;
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
            ->addSelect("movement.state AS state")
            ->addSelect("client.name AS client_name")
            ->addSelect("movement.comment AS comment")
            ->join("movement.box", "box")
            ->join("movement.quality", "quality")
            ->join("movement.client", "client")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params) {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("movement");
        $total = QueryHelper::count($qb, "movement");

        if ($search) {
            $qb->join("movement.box", "search_box")
                ->join("movement.client", "search_client")
                ->join("movement.quality", "search_quality")
                ->join("movement.user", "search_user")
                ->andWhere("search_box.number LIKE :search")
                ->andWhere("search_client.name LIKE :search")
                ->andWhere("search_quality.name LIKE :search")
                ->andWhere("search_user.username LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["filters"] as $name => $value) {
            switch ($name) {
                case "from":
                    $qb->andWhere("DATE(movement.date) >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("DATE(movement.date) <= :to")
                        ->setParameter("to", $value);
                    break;
                case "client":
                    $qb->leftJoin("movement.client", "filter_client")
                        ->andWhere("filter_client.name LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                case "user":
                    $qb->leftJoin("movement.user", "filter_user")
                        ->andWhere("filter_user.username LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                default:
                    $qb->andWhere("location.$name LIKE :filter_$name")
                        ->setParameter("filter_$name", "%$value%");
                    break;
            }
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            $qb->addOrderBy("movement.$column", $order["dir"]);
        }

        $filtered = QueryHelper::count($qb, "movement");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

}
