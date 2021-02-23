<?php

namespace App\Repository;

use App\Entity\Client;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("client")
            ->select("client.name AS name")
            ->addSelect("client.active AS active")
            ->addSelect("client.address AS address")
            ->addSelect("user.username AS username")
            ->leftJoin("client.user", "user")
            ->getQuery()
            ->getResult();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("client");
        $total = QueryHelper::count($qb, "client");

        if ($search) {
            $qb->where("client.name LIKE :search")
                ->orWhere("client.address LIKE :search")
                ->orWhere("client.address LIKE :search")
                ->orWhere("search_user.username LIKE :search")
                ->leftJoin("client.user", "search_user")
                ->setParameter("search", "%$search%");
        }

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

        $filtered = QueryHelper::count($qb, "client");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("client")
            ->select("client.id AS id, client.name AS text")
            ->where("client.name LIKE :search")
            ->andWhere("client.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function getMultiSiteForSelect(?string $search) {
        return $this->createQueryBuilder("client")
            ->select("client.id AS id, client.name AS text")
            ->where("client.name LIKE :search")
            ->andWhere("client.active = 1")
            ->andWhere("client.isMultiSite = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
