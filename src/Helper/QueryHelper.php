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

    public static function withCurrentGroup(QueryBuilder $query, string $field, ?User $user): QueryBuilder {
        dump("lol");
        if ($user && $user->getRole()->isAllowEditOwnGroupOnly() && !$user->getGroups()->isEmpty()) {
            $fields = explode(":", $field);
            if(count($fields) === 2) {
                $fields = $fields[1];
                $multiple = true;
            } else {
                $fields = $fields[0];
                $multiple = false;
            }

            $fields = explode(".", $fields);
            $alias = $fields[0];
            $join = $fields[1] ?? null;
            $field = $fields[2] ?? null;

            if ($field) {
                $query->leftJoin("$alias.$join", "__entity_with_group");
                $alias = "__entity_with_group";
            } else {
                $field = $join;
            }
dump($alias, $join, $field, $user->getGroups());
            $dot = $field ? "." : "";
            if($multiple) {
                foreach ($user->getGroups() as $i => $group) {
                    $query
                        ->andWhere("$alias$dot$field IS EMPTY OR :__group_$i MEMBER OF $alias$dot$field")
                        ->setParameter("__group_$i", $group);
                }
            } else {
                $query->andWhere("$alias$dot$field IS NULL OR $alias$dot$field IN (:__groups)")
                    ->setParameter("__groups", $user->getGroups());
            }
        }

        return $query;
    }

}
