<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/** Numeração sequencial por loja (pedidos, compras), segura sob concorrência. Chamar dentro de transação. */
final class Counter
{
    public static function next(Database $db, int $tenantId, string $name): int
    {
        $db->execute('INSERT INTO tenant_counters (tenant_id, name, value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE value = value', [$tenantId, $name]);
        $v = (int) $db->value('SELECT value FROM tenant_counters WHERE tenant_id = ? AND name = ? FOR UPDATE', [$tenantId, $name]);
        $db->execute('UPDATE tenant_counters SET value = ? WHERE tenant_id = ? AND name = ?', [$v + 1, $tenantId, $name]);
        return $v + 1;
    }
}
