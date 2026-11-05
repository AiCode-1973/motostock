<?php
declare(strict_types=1);

namespace App\Core;

/** Geração de URLs considerando subpasta e o prefixo da loja (/loja/<slug> em desenvolvimento). */
final class Url
{
    private static string $base = '';
    private static string $storePrefix = '';

    public static function setBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    public static function setStorePrefix(string $prefix): void
    {
        self::$storePrefix = rtrim($prefix, '/');
    }

    public static function to(string $path = '/', array $query = []): string
    {
        $u = self::$base . '/' . ltrim($path, '/');
        if ($u === '') {
            $u = '/';
        }
        return $query ? $u . '?' . http_build_query($query) : $u;
    }

    public static function store(string $path = '/', array $query = []): string
    {
        return self::to(self::$storePrefix . '/' . ltrim($path, '/'), $query);
    }

    /** URL absoluta (para e-mails e notificações de gateway). */
    public static function absolute(string $path): string
    {
        return rtrim((string) Env::get('APP_URL', 'http://localhost'), '/') . '/' . ltrim($path, '/');
    }

    /** URL pública da loja de um tenant, conforme a configuração de domínios. */
    public static function storefrontFor(string $slug): string
    {
        $base = Env::get('APP_BASE_DOMAIN', '');
        if (Env::bool('STORE_USE_SUBDOMAINS', false) && $base !== '') {
            $scheme = parse_url((string) Env::get('APP_URL', 'http://localhost'), PHP_URL_SCHEME) ?: 'https';
            $port = parse_url((string) Env::get('APP_URL', ''), PHP_URL_PORT);
            return $scheme . '://' . $slug . '.' . $base . ($port ? ':' . $port : '') . '/';
        }
        return self::absolute('loja/' . $slug);
    }
}
