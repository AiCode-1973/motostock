<?php
declare(strict_types=1);

namespace App\Core;

/** Relógio em UTC, que os testes podem fixar. */
final class Clock
{
    public static ?int $fixed = null;

    public static function ts(): int
    {
        return self::$fixed ?? time();
    }

    public static function now(string $modify = ''): string
    {
        $ts = self::ts();
        if ($modify !== '') {
            $ts = (int) strtotime($modify, $ts);
        }
        return gmdate('Y-m-d H:i:s', $ts);
    }
}
