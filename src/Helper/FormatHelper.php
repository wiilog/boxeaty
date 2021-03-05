<?php

namespace App\Helper;

use App\Entity\User;
use DateTimeInterface;

class FormatHelper {

    public static function entity($entities, string $field, string $separator = ", ") {
        return Stream::from($entities)
            ->filter(function($entity) use ($field) {
                return $entity !== null && is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->map(function($entity) use ($field) {
                return is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->join($separator);
    }

    public static function users($users): ?string {
        return self::entity($users, "username");
    }

    public static function boxes($entities): ?string {
        return self::entity($entities, "number");
    }

    public static function depositTickets($entities): ?string {
        return self::entity($entities, "number");
    }

    public static function named($entity): ?string {
        return $entity ? $entity->getName() : "";
    }

    public static function user(?User $user): ?string {
        return $user ? $user->getUsername() : "";
    }

    public static function bool(?bool $bool, $else = ""): ?string {
        return isset($bool) ? ($bool ? "Oui" : "Non") : $else;
    }

    public static function date(?DateTimeInterface $date, $else = ""): ?string {
        return $date ? $date->format("d/m/Y") : $else;
    }

    public static function datetime(?DateTimeInterface $date, $else = ""): ?string {
        return $date ? $date->format("d/m/Y H:i") : $else;
    }

    public static function time(?DateTimeInterface $date, $else = ""): ?string {
        return $date ? $date->format("H:i") : $else;
    }

    public static function html(?string $comment, $else = ""): ?string {
        return $comment ? strip_tags($comment) : $else;
    }

}
