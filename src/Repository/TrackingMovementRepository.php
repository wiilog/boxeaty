<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\TrackingMovement;
use App\Entity\User;
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
            ->addSelect("location.name AS location_name")
            ->addSelect("box.number AS box_number")
            ->addSelect("quality.name AS quality_name")
            ->addSelect("movement.state AS state")
            ->addSelect("client.name AS client_name")
            ->addSelect("movement.comment AS comment")
            ->leftJoin("movement.box", "box")
            ->leftJoin("movement.quality", "quality")
            ->leftJoin("movement.client", "client")
            ->leftJoin("movement.location", "location")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user) {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("movement");
        QueryHelper::withCurrentGroup($qb, "movement.client.group", $user);

        $total = QueryHelper::count($qb, "movement");

        if ($search) {
            $qb->join("movement.box", "search_box")
                ->join("movement.client", "search_client")
                ->join("movement.quality", "search_quality")
                ->join("movement.user", "search_user")
                ->andWhere($qb->expr()->orX(
                    "search_box.number LIKE :search",
                    "search_client.name LIKE :search",
                    "search_quality.name LIKE :search",
                    "search_user.username LIKE :search"
                ))
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
                    $qb->andWhere("movement.$name = :filter_$name")
                        ->setParameter("filter_$name", $value);
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

    public function findPreviousMovement($box): TrackingMovement {
        return $this->createQueryBuilder("movement")
            ->where("movement.box = :box")
            ->andWhere("movement.location IS NULL")
            ->orderBy("movement.id", "DESC")
            ->setParameter("box", $box)
            ->getQuery()
            ->getSingleResult();
    }

    public function getBoxMovements(Box $box, int $start, int $length): array {
        return $this->createQueryBuilder("tracking_movement")
            ->select("tracking_movement.comment AS comment")
            ->addSelect("tracking_movement.date AS date")
            ->addSelect("tracking_movement.state AS state")
            ->where("tracking_movement.box = :box")
            ->addOrderBy("tracking_movement.date", "DESC")
            ->setParameter("box", $box)
            ->setMaxResults($length)
            ->setFirstResult($start)
            ->getQuery()
            ->getResult();
    }
}
