<?php
declare(strict_types=1);

namespace App\Core;

/** Permissões por função na loja (menor privilégio). Conferidas no backend em toda rota. */
final class Permissions
{
    public const ROLES = [
        'owner' => 'Proprietário',
        'manager' => 'Gerente',
        'seller' => 'Atendente/vendedor',
    ];

    private const MAP = [
        'owner' => ['*'],
        'manager' => [
            'dashboard.view', 'products.view', 'products.manage', 'products.cost', 'stock.view', 'stock.move',
            'suppliers.view', 'suppliers.manage', 'purchases.view', 'purchases.manage',
            'customers.view', 'customers.manage', 'orders.view', 'orders.create', 'orders.manage', 'orders.refund',
            'reports.view', 'reports.export', 'catalog.import', 'settings.view', 'billing.view',
        ],
        'seller' => [
            'dashboard.view', 'products.view', 'stock.view', 'customers.view', 'customers.manage',
            'orders.view', 'orders.create', 'orders.manage',
        ],
    ];

    /** Só o proprietário: usuários, configurações, credenciais, exportação total e privacidade. */
    public const OWNER_ONLY = ['users.manage', 'settings.manage', 'billing.manage', 'data.export', 'privacy.manage'];

    public static function allows(?string $role, string $permission): bool
    {
        if ($role === null || !isset(self::MAP[$role])) {
            return false;
        }
        $list = self::MAP[$role];
        return in_array('*', $list, true) || in_array($permission, $list, true);
    }
}
