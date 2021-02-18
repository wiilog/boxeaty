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

        foreach($params["filters"] as $name => $value) {
            switch($name) {
                case 'from':
                    $qb->andWhere('movement.date >= :from')
                        ->setParameter('from', "%$value%" . " 00:00:00");
                    break;
                case 'to':
                    $qb->andWhere('movement.date <= :to')
                        ->setParameter('to', "%$value%" . " 23:59:59");
                    break;
                case("client"):
                    $qb->leftJoin("movement.client", "filter_client")
                        ->andWhere("filter_client.name LIKE :value")
                        ->setParameter("value", "%$value%");
                break;
                case("operator"):
                    $qb->leftJoin("movement.operator", "filter_operator")
                        ->andWhere("filter_operator.username LIKE :value")
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
