<?php

namespace App\Helper;

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

}
