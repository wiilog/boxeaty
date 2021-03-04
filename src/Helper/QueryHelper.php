<?php

namespace App\Helper;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;

class QueryHelper {

    public static function count(QueryBuilder $query, string $alias): int {
        $countQuery = clone $query;

        return $countQuery
            ->resetDQLPart("orderBy")
            ->resetDQLPart("groupBy")
            ->select("COUNT(DISTINCT $alias) AS __query_count")
            ->getQuery()
            ->getSingleResult()["__query_count"];
    }

    public static function order(QueryBuilder $query, string $field, string $direction): QueryBuilder {
        [$alias, $field, $joinField] = explode(".", $field);

        return $query->leftJoin("$alias.$field", "__order_{$alias}_{$field}")
            ->addOrderBy("__order_{$alias}_{$field}.$joinField", $direction);
    }

    public static function withCurrentGroup(QueryBuilder $query, string $field, User $user): QueryBuilder {
        if ($user->getRole()->isAllowEditOwnGroupOnly()) {
            $fields = explode(".", $field);
            $alias = $fields[0];
            $field = $fields[1] ?? null;

            if ($field) {
                $join = "__current_group_user";
                $query->leftJoin("$alias.$field", $join);
            } else {
                $join = $alias;
            }

            foreach ($user->getGroups() as $i => $group) {
                $query->andWhere(":group_$i MEMBER OF $join.groups")
                    ->setParameter("group_$i", $group);
            }
        }

        return $query;
    }

}
