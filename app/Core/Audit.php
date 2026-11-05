<?php
declare(strict_types=1);

namespace App\Core;

/** Trilha de auditoria. Nunca grave senhas, tokens, dados de cartão ou dados pessoais desnecessários em $meta. */
final class Audit
{
    public static string $ip = '';
    private const BLOCKED_KEYS = ['password', 'senha', 'token', 'secret', 'access_token', 'card', 'cvv', 'webhook_secret', 'password_hash', 'mfa_secret'];

    public static function log(
        string $area,
        string $action,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $entity = null,
        int|string|null $entityId = null,
        array $meta = []
    ): void {
        foreach (array_keys($meta) as $k) {
            foreach (self::BLOCKED_KEYS as $blocked) {
                if (str_contains(strtolower((string) $k), $blocked)) {
                    unset($meta[$k]);
                }
            }
        }
        Database::connect()->insert('audit_logs', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'area' => $area,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'ip' => self::$ip !== '' ? self::$ip : null,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => Clock::now(),
        ]);
    }

    /** Atalho para ações no painel da loja atual. */
    public static function panel(string $action, ?string $entity = null, int|string|null $entityId = null, array $meta = []): void
    {
        self::log('panel', $action, Tenant::idOrNull(), Auth::id(), $entity, $entityId, $meta);
    }
}
