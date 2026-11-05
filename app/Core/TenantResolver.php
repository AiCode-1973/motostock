<?php
declare(strict_types=1);

namespace App\Core;

/** Descobre a loja pelo host da requisição ou pelo prefixo /loja/<slug>. */
final class TenantResolver
{
    public const RESERVED = ['www', 'app', 'admin', 'api', 'painel', 'plataforma', 'loja', 'mail', 'smtp', 'ftp', 'cdn', 'static', 'suporte', 'ajuda', 'blog', 'status', 'motostock'];

    /** @return array{tenant: array, prefix: string, path: string}|null */
    public static function resolve(Database $db, Request $r): ?array
    {
        $tenant = self::fromHost($db, $r->host);
        if ($tenant) {
            return ['tenant' => $tenant, 'prefix' => '', 'path' => $r->path];
        }
        if (Env::bool('STORE_PATH_FALLBACK', true) && preg_match('#^/loja/([a-z0-9-]{3,40})(/.*)?$#', $r->path, $m)) {
            $tenant = self::bySlug($db, $m[1]);
            if ($tenant) {
                return ['tenant' => $tenant, 'prefix' => '/loja/' . $m[1], 'path' => $m[2] ?? '/'];
            }
            throw HttpException::notFound('Loja não encontrada.');
        }
        return null;
    }

    public static function fromHost(Database $db, string $host): ?array
    {
        $host = strtolower($host);
        $base = strtolower((string) Env::get('APP_BASE_DOMAIN', ''));
        $mainHost = strtolower((string) parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST));
        if ($host === '' || $host === $mainHost || $host === $base || $host === 'www.' . $base) {
            return null;
        }
        if ($base !== '' && str_ends_with($host, '.' . $base)) {
            $label = substr($host, 0, -strlen('.' . $base));
            if (str_contains($label, '.') || in_array($label, self::RESERVED, true)) {
                return null;
            }
            return self::bySlug($db, $label);
        }
        if (!Env::bool('ALLOW_CUSTOM_DOMAINS', false)) {
            return null;
        }
        // Domínio próprio: só vale se verificado e se o plano permitir.
        return $db->fetch(
            'SELECT t.* FROM tenant_domains d JOIN tenants t ON t.id = d.tenant_id JOIN plans p ON p.id = t.plan_id
             WHERE d.host = ? AND d.kind = \'custom\' AND d.verified_at IS NOT NULL AND p.allow_custom_domain = 1',
            [$host]
        );
    }

    public static function bySlug(Database $db, string $slug): ?array
    {
        return $db->fetch('SELECT * FROM tenants WHERE slug = ?', [$slug]);
    }
}
