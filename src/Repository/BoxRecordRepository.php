<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method BoxRecord|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoxRecord|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoxRecord[]    findAll()
 * @method BoxRecord[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxRecordRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['date', 'desc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("record")
            ->select("record.date AS date")
            ->addSelect("location.name AS location_name")
            ->addSelect("box.number AS box_number")
            ->addSelect("quality.name AS quality_name")
            ->addSelect("record.state AS state")
            ->addSelect("client.name AS client_name")
            ->addSelect("user.username AS user_username")
            ->where("record.trackingMovement = 1")
            ->leftJoin("record.location", "location")
            ->leftJoin("record.box", "box")
            ->leftJoin("record.quality", "quality")
            ->leftJoin("record.client", "client")
            ->leftJoin("record.user", "user")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user) {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("record")
            ->where('record.trackingMovement = 1');

        QueryHelper::withCurrentGroup($qb, "record.client.group", $user);

        $total = QueryHelper::count($qb, "record");

        if ($search) {
            $qb->leftJoin("record.box", "search_box")
                ->leftJoin("record.client", "search_client")
                ->leftJoin("record.quality", "search_quality")
                ->leftJoin("record.user", "search_user")
                ->andWhere($qb->expr()->orX(
                    "search_box.number LIKE :search",
                    "search_client.name LIKE :search",
                    "search_quality.name LIKE :search",
                    "search_user.username LIKE :search"
                ))
                ->setParameter("search", "%$search%");
        }

        foreach ($params["filters"] ?? [] as $name => $value) {
            switch ($name) {
                case "from":
                    $qb->andWhere("DATE(record.date) >= :from")
                        ->setParameter("from", $value);
                    break;
                case "to":
                    $qb->andWhere("DATE(record.date) <= :to")
                        ->setParameter("to", $value);
                    break;
                case "client":
                    $qb->leftJoin("record.client", "filter_client")
                        ->andWhere("filter_client.id LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                case "user":
                    $qb->leftJoin("record.user", "filter_user")
                        ->andWhere("filter_user.id LIKE :value")
                        ->setParameter("value", "%$value%");
                    break;
                default:
                    $qb->andWhere("record.$name = :filter_$name")
                        ->setParameter("filter_$name", $value);
                    break;
            }
        }

        if (!empty($params['order'])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("record.$column", $order["dir"]);
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("record.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "record");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function findPreviousTrackingMovement(Box $box): ?BoxRecord {
        return $this->createQueryBuilder("record")
            ->where("record.box = :box")
            ->andWhere("record.location IS NOT NULL")
            ->andWhere("record.trackingMovement = 1")
            ->orderBy("record.id", "DESC")
            ->setMaxResults(1)
            ->setParameter("box", $box)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getBoxRecords(Box $box, int $start, int $length, string $search = null): array {
        $qb = $this->createQueryBuilder("record")
            ->select("record.comment AS comment")
            ->addSelect("record.date AS date")
            ->addSelect("record.state AS state")
            ->where("record.box = :box")
            ->andWhere("record.trackingMovement = 0")
            ->orderBy("record.date", "DESC")
            ->addOrderBy("record.id", "DESC")
            ->setParameter("box", $box);

        if($search) {
            $qb
                ->andWhere("record.comment LIKE :search")
                ->setParameter("search", '%' . $search . '%');
        }

        return [
            'totalCount' => QueryHelper::count($qb, 'record'),
            'data' => $qb
                ->setMaxResults($length)
                ->setFirstResult($start)
                ->getQuery()
                ->getResult()
        ];
    }

    public function findNewerTrackingMovement(BoxRecord $trackingMovement): ?BoxRecord {
        $box = $trackingMovement->getBox();
        if ($box
            && $trackingMovement->getId()
            && $trackingMovement->getDate()) {
            return $this->createQueryBuilder("record")
                ->where("record.box = :box")
                ->andWhere("record.id != :movement")
                ->andWhere("record.date > :date")
                ->andWhere("record.trackingMovement = 1")
                ->addOrderBy("record.date", "DESC")
                ->addOrderBy("record.id", "DESC")
                ->setParameter("box", $box)
                ->setParameter("movement", $trackingMovement->getId())
                ->setParameter("date", $trackingMovement->getDate())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return null;
    }
}
