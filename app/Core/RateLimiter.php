<?php
declare(strict_types=1);

namespace App\Core;

/** Limite de tentativas em janela deslizante, guardado no banco (funciona em hospedagem compartilhada). */
final class RateLimiter
{
    public static function tooMany(Database $db, string $bucket, string $key, int $max, int $windowSeconds): bool
    {
        $count = (int) $db->value(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND key_hash = ? AND created_at > ?',
            [$bucket, hash('sha256', $key), Clock::now('-' . $windowSeconds . ' seconds')]
        );
        return $count >= $max;
    }

    public static function hit(Database $db, string $bucket, string $key): void
    {
        $db->insert('rate_limits', [
            'bucket' => $bucket,
            'key_hash' => hash('sha256', $key),
            'created_at' => Clock::now(),
        ]);
    }

    public static function clear(Database $db, string $bucket, string $key): void
    {
        $db->execute('DELETE FROM rate_limits WHERE bucket = ? AND key_hash = ?', [$bucket, hash('sha256', $key)]);
    }

    /** Conta a tentativa e lança 429 se o limite estourar. */
    public static function guard(Database $db, string $bucket, string $key, int $max, int $windowSeconds): void
    {
        if (self::tooMany($db, $bucket, $key, $max, $windowSeconds)) {
            throw new HttpException(429);
        }
        self::hit($db, $bucket, $key);
    }

    public static function purge(Database $db): int
    {
        return $db->execute('DELETE FROM rate_limits WHERE created_at < ?', [Clock::now('-1 day')]);
    }
}
