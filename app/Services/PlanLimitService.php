<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;

/** Mede o uso e aplica os limites do plano do tenant (NULL no plano = sem limite). */
final class PlanLimitService
{
    private const LABELS = [
        'users' => ['max_users', 'usuários'],
        'products' => ['max_products', 'produtos'],
        'orders' => ['max_orders_month', 'pedidos neste mês'],
    ];

    public function __construct(private Database $db, private int $tenantId)
    {
    }

    public function plan(): array
    {
        return $this->db->fetch(
            'SELECT p.* FROM plans p JOIN tenants t ON t.plan_id = p.id WHERE t.id = ?',
            [$this->tenantId]
        ) ?? throw new \LogicException('Tenant sem plano.');
    }

    public function usage(): array
    {
        $monthStart = gmdate('Y-m-01 00:00:00', Clock::ts());
        return [
            'users' => (int) $this->db->value("SELECT COUNT(*) FROM tenant_users WHERE tenant_id = ? AND status = 'active'", [$this->tenantId]),
            'products' => (int) $this->db->value('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$this->tenantId]),
            'orders' => (int) $this->db->value('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND placed_at >= ?', [$this->tenantId, $monthStart]),
        ];
    }

    /** Lança BusinessException com mensagem clara se adicionar $amount ultrapassar o limite. */
    public function assertCanAdd(string $resource, int $amount = 1): void
    {
        [$column, $noun] = self::LABELS[$resource];
        $plan = $this->plan();
        $limit = $plan[$column];
        if ($limit === null) {
            return;
        }
        $used = $this->usage()[$resource];
        if ($used + $amount > (int) $limit) {
            throw new BusinessException(sprintf(
                'Limite do plano %s atingido: até %d %s (em uso: %d). Para continuar, mude de plano em Assinatura.',
                $plan['name'],
                $limit,
                $noun,
                $used
            ));
        }
    }
}
