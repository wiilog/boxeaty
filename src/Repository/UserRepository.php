<?php

namespace App\Repository;

use App\Entity\User;
use App\Helper\QueryCounter;
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
            ->getResult();

    }

    public function findForDatatable(array $params): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("user");
        $total = QueryCounter::count($qb, "user");

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

        $filtered = QueryCounter::count($qb, "user");

        $qb->setFirstResult($params["start"])
            ->setMaxResults($params["length"]);

        return [
            "data" => $qb->getQuery()->getResult(),
            "total" => $total,
            "filtered" => $filtered,
        ];
    }

    public function getForSelect(?string $search) {
        return $this->createQueryBuilder("user")
            ->select("user.id AS id, user.username AS text")
            ->where("user.name LIKE :search")
            ->andWhere("user.active = 1")
            ->setMaxResults(50)
            ->setParameter("search", "%$search%")
            ->getQuery()
            ->getArrayResult();
    }

}
