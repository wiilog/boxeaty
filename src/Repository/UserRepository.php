<?php

namespace App\Repository;

use App\Entity\User;
use App\Helper\QueryHelper;
use Doctrine\ORM\EntityRepository;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository {

    public function iterateAll() {
        return $this->createQueryBuilder("user")
            ->select("user.username AS username")
            ->addSelect("user.email AS email")
            ->addSelect("role.name AS role_name")
            ->addSelect("user.active AS active")
            ->addSelect("user.creationDate AS creationDate")
            ->addSelect("user.lastLogin as lastLogin")
            ->join("user.role", "role")
            ->getQuery()
            ->iterate();
    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("user");
        $total = QueryHelper::count($qb, "user");

        if ($search) {
            $qb->where("user.username LIKE :search")
                ->orWhere("user.email LIKE :search")
                ->setParameter("search", "%$search%");
        }

        foreach ($params["order"] ?? [] as $order) {
            $column = $params["columns"][$order["column"]]["data"];
            if ($column === "role") {
                $qb->join("user.role", "role")
                    ->addOrderBy("role.name", $order["dir"]);
            } else {
                $qb->addOrderBy("user.$column", $order["dir"]);
            }
        }

        $filtered = QueryHelper::count($qb, "user");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search): array {
        return $this->createQueryBuilder("user")
            ->select("user.id AS id, user.username AS text")
            ->where("user.username LIKE :search")
            ->andWhere("user.active = 1")
            ->setMaxResults(15)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

    public function findNewUserRecipients($group) {
        return $this->createQueryBuilder("user")
            ->join("user.role", "role")
            ->leftJoin("user.groups", "groups")
            ->where("role.receiveMailsNewAccounts = 1")
            ->andWhere("role.allowEditOwnGroupOnly = 0 OR :group MEMBER OF user.groups")
            ->andWhere("user.active = 1")
            ->setParameter("group", $group)
            ->getQuery()
            ->getResult();
    }

}
