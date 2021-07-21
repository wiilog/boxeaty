<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends EntityRepository {

    public const DEFAULT_DATATABLE_ORDER = [['name', 'asc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("client")
            ->select("client.name AS name")
            ->addSelect("client.active AS active")
            ->addSelect("client.address AS address")
            ->addSelect("join_contact.username AS assignedContact")
            ->addSelect("join_group.name AS group")
            ->addSelect("join_linkedMultiSite.name AS multiSite")
            ->addSelect("client.isMultiSite AS isMultiSite")
            ->leftJoin("client.group", "join_group")
            ->leftJoin("client.linkedMultiSite", "join_linkedMultiSite")
            ->leftJoin("client.contact", "join_contact")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("client");
        QueryHelper::withCurrentGroup($qb, "client.group", $user);

        $total = QueryHelper::count($qb, "client");

        if ($search) {
            $qb->leftJoin("client.users", "search_user")
                ->leftJoin("client.group", "search_group")
                ->leftJoin("client.linkedMultiSite", "search_multi_site")
                ->andWhere($qb->expr()->orX(
                    "client.name LIKE :search",
                    "client.address LIKE :search",
                    "search_user.username LIKE :search",
                    "search_group.name LIKE :search",
                    "search_multi_site.name LIKE :search",
                ))
                ->setParameter("search", "%$search%");
        }

        if (!empty($params["order"])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "contact") {
                    QueryHelper::order($qb, "client.contact.username", $order["dir"]);
                } else if ($column === "group") {
                    QueryHelper::order($qb, "client.group.name", $order["dir"]);
                } else if ($column === "multiSite") {
                    QueryHelper::order($qb, "client.multiSite.name", $order["dir"]);
                } else {
                    $qb->addOrderBy("client.$column", $order["dir"]);
                }
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("client.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "client");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search,
                                 ?string $group,
                                 ?User $user,
                                 ?bool $costInformationNeeded = false) {
        $qb = $this->createQueryBuilder("client");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        if($group) {
            $qb->andWhere("client.group = :group")
                ->setParameter("group", $group);
        }

        $qb
            ->select("client.id AS id")
            ->addSelect("client.name AS text")
            ->addSelect("information.workingDayDeliveryRate AS workingRate")
            ->addSelect("information.nonWorkingDayDeliveryRate AS nonWorkingRate")
            ->addSelect("information.serviceCost AS serviceCost")
            ->addSelect("client.address AS address")
            ->leftjoin("client.clientOrderInformation", "information")
            ->andWhere("client.name LIKE :search")
            ->andWhere("client.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%");

        if ($costInformationNeeded) {
            $qb
                ->andWhere("information.workingDayDeliveryRate IS NOT NULL")
                ->andWhere("information.nonWorkingDayDeliveryRate IS NOT NULL")
                ->andWhere("information.serviceCost IS NOT NULL");
        }

        return $qb
            ->getQuery()
            ->getArrayResult();
    }

    public function getMultiSiteForSelect(?string $search, ?User $user) {
        $qb = $this->createQueryBuilder("client");

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $qb->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        return $qb->select("client.id AS id, client.name AS text")
            ->andWhere("client.name LIKE :search")
            ->andWhere("client.active = 1")
            ->andWhere("client.isMultiSite = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
