<?php

namespace App\Repository;

use App\Entity\Box;
use App\Entity\Depository;
use App\Entity\User;
use App\Helper\QueryHelper;
use App\Service\BoxStateService;
use Doctrine\ORM\EntityRepository;

/**
 * @method Box|null find($id, $lockMode = null, $lockVersion = null)
 * @method Box|null findOneBy(array $criteria, array $orderBy = null)
 * @method Box[]    findAll()
 * @method Box[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoxRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['creationDate', 'DESC']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("box")
            ->select("box.number AS number")
            ->addSelect("box.creationDate AS creationDate")
            ->addSelect("box.isBox AS isBox")
            ->addSelect("join_location.name AS location")
            ->addSelect("box.state AS state")
            ->addSelect("join_quality.name AS quality")
            ->addSelect("join_owner.name AS owner")
            ->addSelect("join_type.name AS type")
            ->leftJoin("box.location", "join_location")
            ->leftJoin("box.quality", "join_quality")
            ->leftJoin("box.owner", "join_owner")
            ->leftJoin("box.type", "join_type")
            ->getQuery()
            ->toIterable();
    }

    public function getForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("box");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("box.owner", "owner")
                ->andWhere("owner.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("box.id AS id, box.number AS text")
            ->andWhere("box.number LIKE :search")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getForOrderSelect(?string $search, ?array $exclude, ?User $user) {
        $qb = $this->createQueryBuilder("box");

        if($exclude) {
            $qb->andWhere("box.number NOT IN (:excluded)")
                ->setParameter("excluded", $exclude);
        }

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->join("box.owner", "owner")
                ->andWhere("owner.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("box.id AS id, box.number AS text, type.price AS price")
            ->join("box.type", "type")
            ->andWhere("box.number LIKE :search")
            ->andWhere("box.state = '" . BoxStateService::STATE_BOX_CLIENT . "'")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("box");
        QueryHelper::withCurrentGroup($qb, "box.owner.group", $user);

        $total = QueryHelper::count($qb, "box");

        if ($search) {
            $qb->leftJoin("box.location", "search_location")
                ->leftJoin("box.owner", "search_owner")
                ->leftJoin("box.quality", "search_quality")
                ->leftJoin("box.type", "search_type")
                ->andWhere($qb->expr()->orX(
                    "box.number LIKE :search",
                    "search_location.name LIKE :search",
                    "search_owner.name LIKE :search",
                    "search_quality.name LIKE :search",
                    "search_type.name LIKE :search",
                    "DATE_FORMAT(box.creationDate, '%d/%m/%Y') LIKE :search",
                    "DATE_FORMAT(box.creationDate, '%H:%i') LIKE :search"
                ))
                ->setParameter("search", "%$search%");
        }

        foreach ($params["filters"] ?? [] as $name => $value) {
            switch ($name) {
                case("group"):
                    $qb->leftJoin("box.owner", "filter_client")
                        ->andWhere("filter_client.group = :filter_group")
                        ->setParameter("filter_group", $value);
                    break;
                case("client"):
                    $qb->andWhere("box.owner = :filter_owner")
                        ->setParameter("filter_owner", $value);
                    break;
                case("creationDate"):
                    $qb->andWhere("DATE(box.creationDate) = :value")
                        ->setParameter("value", $value);
                    break;
                case("depository"):
                    $qb->leftJoin("box.location", "filter_location")
                        ->andWhere("filter_location.depository = :filter_depository")
                        ->setParameter("filter_depository", $value);
                    break;
                case("box"):
                    $qb->andWhere("box.isBox = :filter_isbox")
                        ->setParameter("filter_isbox", $value);
                    break;
                default:
                    $qb->andWhere("box.$name = :filter_$name")
                        ->setParameter("filter_$name", $value);
                    break;
            }
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "location") {
                    $qb->leftJoin("box.location", "order_location")
                        ->addOrderBy("order_location.name", $order["dir"]);
                } else if ($column === "quality") {
                    $qb->leftJoin("box.quality", "order_quality")
                        ->addOrderBy("order_quality.name", $order["dir"]);
                } else if ($column === "owner") {
                    $qb->leftJoin("box.owner", "order_owner")
                        ->addOrderBy("order_owner.name", $order["dir"]);
                } else if ($column === "type") {
                    $qb->leftJoin("box.type", "order_type")
                        ->addOrderBy("order_type.name", $order["dir"]);
                } else {
                    $qb->addOrderBy("box.$column", $order["dir"]);
                }
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("box.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "box");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getByDepository(Depository $depository)
    {
        return $this->createQueryBuilder('crate')
            ->select('crate.id AS crateId')
            ->addSelect('crate.number AS crateNumber')
            ->addSelect('location.name AS crateLocation')
            ->addSelect('type.name AS crateType')
            ->leftJoin("crate.location", "location")
            ->leftJoin("crate.type", "type")
            ->andWhere("location.depository = :depository")
            ->andWhere('crate.isBox = 0')
            ->andWhere('location.type = 1')
            ->setParameter("depository", $depository)
            ->getQuery()
            ->execute();
    }

    public function getByNumber(string $number)
    {
        return $this->createQueryBuilder('box')
            ->select('box.id AS boxId')
            ->addSelect('box.number AS boxNumber')
            ->addSelect('type.name AS boxType')
            ->leftJoin("box.location", "location")
            ->leftJoin("box.type", "type")
            ->andWhere('box.isBox = 1')
            ->andWhere('box.number = :number')
            ->andWhere('box.state = 1')
            ->setParameter("number", $number)
            ->getQuery()
            ->getOneOrNullResult();
    }

}
