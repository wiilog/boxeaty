<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use App\Helper\QueryHelper;
use DateTime;
use Doctrine\ORM\EntityRepository;
use WiiCommon\Helper\Stream;

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

    public function iterateAll(Client $client = null): iterable {
        $qb = $this->createQueryBuilder("client")
            ->select("client.name AS name")
            ->addSelect("client.active AS active")
            ->addSelect("client.address AS address")
            ->addSelect("join_contact.username AS assignedContact")
            ->addSelect("join_group.name AS group")
            ->addSelect("join_linkedMultiSite.name AS multiSite")
            ->addSelect("client.isMultiSite AS isMultiSite")
            ->leftJoin("client.group", "join_group")
            ->leftJoin("client.linkedMultiSite", "join_linkedMultiSite")
            ->leftJoin("client.contact", "join_contact");

        if ($client) {
            $qb->where("client = :client")
                ->setParameter("client", $client);
        }

        return $qb->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("client");
        QueryHelper::withCurrentGroup($qb, "client.group", $user);

        $total = QueryHelper::count($qb, "client");

        if($search) {
            $qb->leftJoin("client.users", "search_user")
                ->leftJoin("client.group", "search_group")
                ->leftJoin("client.contact", "search_contact")
                ->leftJoin("client.linkedMultiSite", "search_multi_site")
                ->andWhere($qb->expr()->orX(
                    "client.name LIKE :search",
                    "client.address LIKE :search",
                    "search_user.username LIKE :search",
                    "search_contact.username LIKE :search",
                    "search_group.name LIKE :search",
                    "search_multi_site.name LIKE :search",
                ))
                ->setParameter("search", "%$search%");
        }

        foreach($params["filters"] ?? [] as $name => $value) {
            switch($name) {
                case("client"):
                    $qb
                        ->andWhere("client.id = :filter_client")
                        ->setParameter("filter_client", $value);
                    break;
                case("multiSite"):
                    $qb->andWhere("client.linkedMultiSite = :filter_multiSite")
                        ->setParameter("filter_multiSite", $value);
                    break;
                default:
                    $qb->andWhere("client.$name = :filter_$name")
                        ->setParameter("filter_$name", $value);
                    break;
            }
        }

        if(!empty($params["order"])) {
            foreach($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if($column === "contact") {
                    QueryHelper::order($qb, "client.contact.username", $order["dir"]);
                } else if($column === "group") {
                    QueryHelper::order($qb, "client.group.name", $order["dir"]);
                } else if($column === "linkedMultiSite") {
                    QueryHelper::order($qb, "client.linkedMultiSite.name", $order["dir"]);
                } else {
                    $qb->addOrderBy("client.$column", $order["dir"]);
                }
            }
        } else {
            foreach(self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
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

    public function getForSelect(?string $search, ?string $group, ?User $user, ?bool $clientWithInformation = false) {
        $qb = $this->createQueryBuilder("client");
        $expr = $qb->expr();

        if($user && $user->getRole()->isAllowEditOwnGroupOnly()) {
            $userClients = $user->getClients();

            if(!$userClients->isEmpty()) {
                $qb->leftJoin("client.linkedMultiSite", "linkedMultiSite")
                    ->andWhere($expr->orX(
                        "client IN (:userClients)",
                        "linkedMultiSite IN (:userClients)"
                    ))
                    ->setParameter('userClients', $userClients);
            }

            $qb->andWhere("client.group IN (:groups)")
                ->setParameter("groups", $user->getGroups());
        }

        if($group) {
            $qb->andWhere("client.group = :group")
                ->setParameter("group", $group);
        }

        $qb->select("client.id AS id")
            ->addSelect("client.name AS text")
            ->addSelect("join_information.workingDayDeliveryRate AS workingRate")
            ->addSelect("join_information.nonWorkingDayDeliveryRate AS nonWorkingRate")
            ->addSelect("join_information.serviceCost AS serviceCost")
            ->addSelect("client.address AS address")
            ->addSelect("join_deliveryMethod.id AS deliveryMethod")
            ->leftjoin("client.clientOrderInformation", "join_information")
            ->leftjoin("join_information.deliveryMethod", "join_deliveryMethod")
            ->andWhere("client.name LIKE :search")
            ->andWhere("client.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%");

        if($clientWithInformation) {
            $qb->andWhere("client.isMultiSite = 0")
                ->andWhere("join_information.workingDayDeliveryRate IS NOT NULL")
                ->andWhere("join_information.nonWorkingDayDeliveryRate IS NOT NULL")
                ->andWhere("join_information.serviceCost IS NOT NULL");
        }

        return $qb->getQuery()->getArrayResult();
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

    public function findActiveRecurrence(): array {
        $now = new DateTime("today midnight");

        return $this->createQueryBuilder("client")
            ->join("client.clientOrderInformation", "client_order_information")
            ->join("client.cratePatternLines", "crate_pattern_lines")
            ->join("client_order_information.deliveryMethod", "delivery_method")
            ->join("client_order_information.depository", "depository")
            ->join("client_order_information.orderRecurrence", "recurrence")
            ->andWhere("client.active = 1")
            ->andWhere("recurrence.id IS NOT NULL")
            ->andWhere("recurrence.start <= :now")
            ->andWhere("recurrence.end >= :now")
            ->andWhere("crate_pattern_lines.id IS NOT NULL")
            ->andWhere("delivery_method.id IS NOT NULL")
            ->andWhere("depository.id IS NOT NULL")
            ->andWhere("client_order_information.workingDayDeliveryRate IS NOT NULL")
            ->andWhere("client_order_information.nonWorkingDayDeliveryRate IS NOT NULL")
            ->andWhere("DAY(recurrence.lastEdit) = DAY(:now)")
            ->andWhere("MONTH(recurrence.lastEdit) = MONTH(:now)")
            ->setParameter("now", $now)
            ->getQuery()
            ->getResult();
    }

    public function getCratePatternAmountGroupedByClient(): array {
        $clients = $this->createQueryBuilder("client")
            ->andWhere('client.cratePatternLines IS NOT EMPTY')
            ->getQuery()
            ->getResult();

        return Stream::from($clients)
            ->keymap(fn(Client $client) => [$client->getId(), $client->getCratePatternAmount()])
            ->toArray();
    }

}
