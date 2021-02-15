<?php

namespace App\Repository;

use App\Entity\User;
use App\Helper\QueryCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends EntityRepository {

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
                    ->addOrderBy("role.label", $order["dir"]);
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

}
