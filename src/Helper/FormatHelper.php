<?php

namespace App\Helper;

use WiiCommon\Helper\Stream;
use App\Entity\User;
use DateTimeInterface;

class FormatHelper {

    public const DATE_COMPACT = "Ymd";
    public const DATE_FORMAT = "d/m/Y";
    public const DATETIME_FORMAT = "d/m/Y H:i";

    public const ENGLISH_WEEK_DAYS = [
        1 => "Monday",
        2 => "Tuesday",
        3 => "Wednesday",
        4 => "Thursday",
        5 => "Friday",
        6 => "Saturday",
        7 => "Sunday",
    ];

    public const WEEK_DAYS = [
        1 => "Lundi",
        2 => "Mardi",
        3 => "Mercredi",
        4 => "Jeudi",
        5 => "Vendredi",
        6 => "Samedi",
        7 => "Dimanche",
    ];

    public const MONTHS = [
        1 => "Janvier",
        2 => "Février",
        3 => "Mars",
        4 => "Avril",
        5 => "Mai",
        6 => "Juin",
        7 => "Juillet",
        8 => "Août",
        9 => "Septembre",
        10 => "Octobre",
        11 => "Novembre",
        12 => "Décembre",
    ];

    public static function entity($entities, string $field, string $separator = ", ", string $else = "-") {
        return Stream::from($entities)
            ->filter(function($entity) use ($field) {
                return $entity !== null && is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->map(function($entity) use ($field) {
                return is_array($entity) ? $entity[$field] : $entity->{"get$field"}();
            })
            ->join($separator) ?: $else;
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

    public static function named($entity, string $else = "-"): ?string {
        return $entity ? $entity->getName() : $else;
    }

    public static function user(?User $user, $else = "-"): ?string {
        return $user ? $user->getUsername() : $else;
    }

    public static function bool(?bool $bool, $else = "-"): ?string {
        return isset($bool) ? ($bool ? "Oui" : "Non") : $else;
    }

    public static function weekDay(?DateTimeInterface $date, $else = "-"): ?string {
        return $date ? self::WEEK_DAYS[$date->format("N")] : $else;
    }

    public static function month(?DateTimeInterface $date, $else = "-"): ?string {
        return $date ? self::MONTHS[$date->format("n")] : $else;
    }

    public static function date(?DateTimeInterface $date, $format = self::DATE_FORMAT, $else = "-"): ?string {
        return $date ? $date->format($format) : $else;
    }

    public static function dateMonth(?DateTimeInterface $date, $else = "-"): ?string {
        return $date
            ? ($date->format("d") . " " . self::MONTHS[$date->format("n")] . " " . $date->format("Y"))
            : $else;
    }

    public static function datetime(?DateTimeInterface $date, $format = self::DATETIME_FORMAT, $else = "-"): ?string {
        return $date ? $date->format($format) : $else;
    }

    public static function time(?DateTimeInterface $date, $else = "-"): ?string {
        return $date ? $date->format("H:i") : $else;
    }

    public static function html(?string $comment, $else = ""): ?string {
        return $comment ? strip_tags($comment) : $else;
    }

    public static function price(?float $priceFloat, bool $showSymbol = true, $else = "-"): string {
        return $priceFloat !== null
            ? (number_format($priceFloat, 2, ',', ' ') . ($showSymbol ? ' €' : ''))
            : $else;
    }

}
