<?php

namespace App\Helper;

use Doctrine\ORM\QueryBuilder;

class QueryCounter {

    public static function count(QueryBuilder $query, string $alias): int {
        $countQuery = clone $query;

        return $countQuery
            ->resetDQLPart("orderBy")
            ->resetDQLPart("groupBy")
            ->select("COUNT(DISTINCT $alias) AS __query_count")
            ->getQuery()
            ->getSingleResult()["__query_count"];
    }

}
