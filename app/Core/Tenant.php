<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tenant da requisição atual, definido SOMENTE pelo servidor:
 *  - painel: sessão + vínculo ativo em tenant_users (revalidado a cada requisição);
 *  - loja: host (subdomínio/domínio verificado) ou prefixo /loja/<slug>.
 */
final class Tenant
{
    private static ?array $current = null;
    private static ?string $role = null;
    private static string $source = '';

    public static function set(array $tenant, ?string $role, string $source): void
    {
        self::$current = $tenant;
        self::$role = $role;
        self::$source = $source;
    }

    public static function clear(): void
    {
        self::$current = null;
        self::$role = null;
        self::$source = '';
    }

    public static function id(): int
    {
        if (self::$current === null) {
            throw new \LogicException('Tenant não definido para esta requisição.');
        }
        return (int) self::$current['id'];
    }

    public static function idOrNull(): ?int
    {
        return self::$current ? (int) self::$current['id'] : null;
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    public static function role(): ?string
    {
        return self::$role;
    }

    public static function source(): string
    {
        return self::$source;
    }

    public static function can(string $permission): bool
    {
        return self::$source === 'panel' && Permissions::allows(self::$role, $permission);
    }

    public static function timezone(): string
    {
        return (string) (self::$current['timezone'] ?? 'America/Sao_Paulo');
    }

    /** Situações em que a loja e o painel operam normalmente. */
    public static function isOperational(array $tenant): bool
    {
        return in_array($tenant['status'], ['trial', 'active', 'past_due'], true);
    }
}
