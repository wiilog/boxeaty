<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\BoxRecord;
use App\Entity\Client;
use App\Entity\User;
use App\Helper\QueryHelper;
use App\Service\BoxService;
use Doctrine\Common\Collections\Criteria;
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

    public function iterateAll(Client $client = null): iterable {
        $qb = $this->createQueryBuilder("record")
            ->select("record.date AS date")
            ->addSelect("location.name AS location_name")
            ->addSelect("box.number AS box_number")
            ->addSelect("quality.name AS quality_name")
            ->addSelect("record.state AS state")
            ->addSelect("client.name AS client_name")
            ->addSelect("user.username AS user_username")
            ->andWhere("record.trackingMovement = 1")
            ->leftJoin("record.location", "location")
            ->leftJoin("record.box", "box")
            ->leftJoin("record.quality", "quality")
            ->leftJoin("record.client", "client")
            ->leftJoin("record.user", "user");

        if ($client) {
            $qb->andWhere("client = :client")
                ->setParameter("client", $client);
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user) {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("record")
            ->andWhere('record.trackingMovement = 1');

        QueryHelper::withCurrentGroup($qb, "record.client.group", $user);

        $total = QueryHelper::count($qb, "record");

        if($search) {
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

        foreach($params["filters"] ?? [] as $name => $value) {
            switch($name) {
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

        if(!empty($params["order"])) {
            foreach($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                $qb->addOrderBy("record.$column", $order["dir"]);
            }
        } else {
            foreach(self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
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
            ->andWhere("record.box = :box")
            ->andWhere("record.location IS NOT NULL")
            ->andWhere("record.trackingMovement = 1")
            ->orderBy("record.id", "DESC")
            ->setMaxResults(1)
            ->setParameter("box", $box)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getBoxRecords(Box $box, int $start, int $length, string $search = null): array {
        $queryBuilder = $this->createQueryBuilder("record");

        $exprBuilder = $queryBuilder->expr();

        $queryBuilder
            ->select("record.id AS id")
            ->addSelect("join_quality.name AS quality")
            ->addSelect("record.date AS date")
            ->addSelect("record.state AS state")
            ->addSelect("join_operator.username AS operator")
            ->addSelect("join_location.name AS location")
            ->addSelect("join_depository.name AS depository")
            ->addSelect("join_crate.number AS crateNumber")
            ->addSelect("join_crate.id AS crateId")
            ->addSelect("join_client.name AS client")
            ->leftJoin("record.user", "join_operator")
            ->leftJoin("record.quality", "join_quality")
            ->leftJoin("record.location", "join_location")
            ->leftJoin("join_location.depository", "join_depository")
            ->leftJoin("record.crate", "join_crate")
            ->leftJoin("record.client", "join_client")
            ->andWhere("record.box = :box")
            ->andWhere("record.trackingMovement = 0")
            ->orderBy("record.date", "DESC")
            ->addOrderBy("record.id", "DESC")
            ->setParameter("box", $box);

        if($search) {
            $queryBuilder
                ->andWhere($exprBuilder->orX(
                    "join_quality.name LIKE :search",
                    "join_location.name LIKE :search",
                    "join_depository.name LIKE :search",
                    "join_operator.username LIKE :search",
                    "DATE(record.date) LIKE :search"
                ))
                ->setParameter("search", '%' . $search . '%');
        }

        return [
            "totalCount" => QueryHelper::count($queryBuilder, "record"),
            "data" => $queryBuilder
                ->setMaxResults($length)
                ->setFirstResult($start)
                ->getQuery()
                ->getResult(),
        ];
    }

    public function findNewerTrackingMovement(BoxRecord $record): ?BoxRecord {
        $box = $record->getBox();
        if($box && $record->getId() && $record->getDate()) {
            return $this->createQueryBuilder("record")
                ->andWhere("record.box = :box")
                ->andWhere("record.id != :movement")
                ->andWhere("record.date > :date")
                ->andWhere("record.trackingMovement = 1")
                ->addOrderBy("record.date", "DESC")
                ->addOrderBy("record.id", "DESC")
                ->setParameter("box", $box)
                ->setParameter("movement", $record->getId())
                ->setParameter("date", $record->getDate())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        return null;
    }

    public function getNumberBoxByStateAndDate(\DateTime $startDate, \DateTime $endDate, int $state, array $clients) {
        return $this->createQueryBuilder("record")
            ->select("COUNT(DISTINCT record.id) AS result")
            ->andWhere("record.date BETWEEN :dateMin AND :dateMax")
            ->andWhere("record.state = :state")
            ->andWhere("record.client in (:clients)")
            ->setParameter("dateMin", $startDate)
            ->setParameter("dateMax", $endDate)
            ->setParameter("state", $state)
            ->setParameter("clients", $clients)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findCurrentBoxRecord(Box $box): ?BoxRecord {
        return $this->createQueryBuilder("record")
            ->andWhere("record.box = :box")
            ->andWhere("record.trackingMovement = 0 OR record.state IN (:packingStates)")
            ->orderBy("record.date", Criteria::DESC)
            ->addOrderBy("record.id", Criteria::DESC)
            ->setMaxResults(1)
            ->setParameter("box", $box)
            ->setParameter("packingStates", [
                BoxService::STATE_RECORD_PACKING,
                BoxService::STATE_RECORD_UNPACKING,
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

}
