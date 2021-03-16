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

    public const DEFAULT_DATATABLE_ORDER = [['id', 'desc']];
    private const DEFAULT_DATATABLE_START = 0;
    private const DEFAULT_DATATABLE_LENGTH = 10;

    public function iterateAll() {
        return $this->createQueryBuilder("user")
            ->select("user.username AS username")
            ->addSelect("user.email AS email")
            ->addSelect("role.name AS role_name")
            ->addSelect("IF(user.active = 1, 'Actif', 'Inactif') AS active")
            ->addSelect("user.lastLogin as lastLogin")
            ->join("user.role", "role")
            ->getQuery()
            ->toIterable();
    }

    public function findForDatatable(array $params, ?User $user): array {
        $search = $params["search"]["value"] ?? null;

        $qb = $this->createQueryBuilder("user");
        QueryHelper::withCurrentGroup($qb, "multiple:user.groups", $user);

        $total = QueryHelper::count($qb, "user");

        if ($search) {
            $qb->andWhere("user.username LIKE :search OR user.email LIKE :search")
                ->setParameter("search", "%$search%");
        }

        if (!empty($params['order'])) {
            foreach ($params["order"] ?? [] as $order) {
                $column = $params["columns"][$order["column"]]["data"];
                if ($column === "role") {
                    $qb->join("user.role", "role")
                        ->addOrderBy("role.name", $order["dir"]);
                } else {
                    $qb->addOrderBy("user.$column", $order["dir"]);
                }
            }
        }
        else {
            foreach (self::DEFAULT_DATATABLE_ORDER as [$column, $dir]) {
                $qb->addOrderBy("user.$column", $dir);
            }
        }

        $filtered = QueryHelper::count($qb, "user");

        $qb->setFirstResult($params["start"] ?? self::DEFAULT_DATATABLE_START)
            ->setMaxResults($params["length"] ?? self::DEFAULT_DATATABLE_LENGTH);

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

    public function findByUsernameOrEmail($search) {
        return $this->createQueryBuilder("user")
            ->where("user.email LIKE :search OR user.username LIKE :search")
            ->setParameter("search", $search)
            ->getQuery()
            ->getResult();
    }

}
