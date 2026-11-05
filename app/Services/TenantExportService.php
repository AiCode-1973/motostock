<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Exporta todos os dados de UM tenant em JSON (portabilidade, encerramento de contrato, migração para banco dedicado).
 * Segredos (hashes de senha, credenciais de gateway, tokens) nunca entram.
 */
final class TenantExportService
{
    /** tabela => colunas excluídas */
    private const TABLES = [
        'store_settings' => [],
        'categories' => [],
        'brands' => [],
        'products' => [],
        'product_images' => [],
        'product_fitments' => [],
        'suppliers' => [],
        'purchase_orders' => [],
        'purchase_order_items' => [],
        'customers' => ['password_hash'],
        'customer_addresses' => [],
        'shipping_methods' => [],
        'orders' => ['tracking_token_hash'],
        'order_items' => [],
        'order_status_history' => [],
        'stock_movements' => [],
        'payments' => ['checkout_url'],
        'data_requests' => [],
    ];

    public function __construct(private Database $db)
    {
    }

    public function export(int $tenantId): string
    {
        $tenant = $this->db->fetch('SELECT id, uuid, slug, trade_name, legal_name, document, email, phone, city, state, status, timezone, created_at FROM tenants WHERE id = ?', [$tenantId]);
        $data = [
            'format' => 'motostock-export/1',
            'generated_at_utc' => gmdate('c'),
            'tenant' => $tenant,
            'team' => $this->db->fetchAll('SELECT u.name, u.email, tu.role, tu.status FROM tenant_users tu JOIN users u ON u.id = tu.user_id WHERE tu.tenant_id = ?', [$tenantId]),
        ];
        foreach (self::TABLES as $table => $exclude) {
            $rows = $this->db->fetchAll("SELECT * FROM {$table} WHERE tenant_id = ?", [$tenantId]);
            $data['tables'][$table] = array_map(fn($r) => array_diff_key($r, array_flip($exclude)), $rows);
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
