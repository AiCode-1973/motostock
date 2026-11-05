<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Period;

/**
 * Dashboard e relatórios de UMA loja. Regras (também em docs/02):
 *  - Faturamento: soma de orders.total_cents com pagamento confirmado (pago, separando, enviado, entregue),
 *    pela data do PAGAMENTO (paid_at), no fuso da loja. Cancelados e reembolsados ficam fora.
 *  - Pedidos recebidos: pedidos criados (placed_at) no período, em qualquer situação.
 *  - Ticket médio: faturamento ÷ número de pedidos pagos no período.
 *  - Mais vendidos: quantidade em pedidos pagos no período.
 *  - Estoque baixo: ativos com disponível (físico − reservado) ≤ estoque mínimo.
 *  - Pendentes: aguardando pagamento, pagos e em separação (exigem ação da loja).
 */
final class ReportService
{
    public const PAID = "('paid','picking','shipped','delivered')";

    public const REPORTS = [
        'vendas' => 'Vendas por pedido',
        'produtos' => 'Produtos vendidos',
        'pagamentos' => 'Pagamentos',
        'estoque' => 'Posição de estoque',
        'compras' => 'Compras por fornecedor',
    ];

    public function __construct(private Database $db, private int $tenantId)
    {
    }

    public function dashboard(Period $p): array
    {
        $t = $this->tenantId;
        $s = $p->startUtc();
        $e = $p->endUtc();
        $rev = $this->db->fetch(
            'SELECT COALESCE(SUM(total_cents), 0) AS revenue, COUNT(*) AS paid_orders FROM orders
             WHERE tenant_id = ? AND status IN ' . self::PAID . ' AND paid_at >= ? AND paid_at < ?',
            [$t, $s, $e]
        );
        $placed = (int) $this->db->value('SELECT COUNT(*) FROM orders WHERE tenant_id = ? AND placed_at >= ? AND placed_at < ?', [$t, $s, $e]);
        $offset = $p->offset();
        $daily = $this->db->fetchAll(
            'SELECT DATE(CONVERT_TZ(paid_at, \'+00:00\', ?)) AS day, SUM(total_cents) AS revenue, COUNT(*) AS orders
             FROM orders WHERE tenant_id = ? AND status IN ' . self::PAID . ' AND paid_at >= ? AND paid_at < ?
             GROUP BY day ORDER BY day',
            [$offset, $t, $s, $e]
        );
        return [
            'revenue' => (int) $rev['revenue'],
            'paid_orders' => (int) $rev['paid_orders'],
            'placed_orders' => $placed,
            'avg_ticket' => (int) $rev['paid_orders'] > 0 ? intdiv((int) $rev['revenue'], (int) $rev['paid_orders']) : 0,
            'top_products' => $this->topProducts($p, 5),
            'low_stock' => (new \App\Repositories\ProductRepository($this->db, $t))->lowStock(8),
            'pending' => $this->db->fetchAll(
                "SELECT status, COUNT(*) AS n FROM orders WHERE tenant_id = ? AND status IN ('awaiting_payment','paid','picking') GROUP BY status",
                [$t]
            ),
            'daily' => $daily,
            'recent' => $this->db->fetchAll('SELECT id, number, customer_name, status, total_cents, placed_at FROM orders WHERE tenant_id = ? ORDER BY id DESC LIMIT 6', [$t]),
        ];
    }

    public function topProducts(Period $p, int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT i.product_id, i.sku, i.name, SUM(i.quantity) AS qty, SUM(i.total_cents) AS revenue
             FROM order_items i JOIN orders o ON o.tenant_id = i.tenant_id AND o.id = i.order_id
             WHERE i.tenant_id = ? AND o.status IN ' . self::PAID . ' AND o.paid_at >= ? AND o.paid_at < ?
             GROUP BY i.product_id, i.sku, i.name ORDER BY qty DESC, revenue DESC LIMIT ?',
            [$this->tenantId, $p->startUtc(), $p->endUtc(), $limit]
        );
    }

    /** @return array{columns: array, rows: array, totals: array, note: string} */
    public function run(string $key, Period $p, array $f = []): array
    {
        $t = $this->tenantId;
        $s = $p->startUtc();
        $e = $p->endUtc();
        switch ($key) {
            case 'vendas':
                $where = 'o.tenant_id = ? AND o.placed_at >= ? AND o.placed_at < ?';
                $params = [$t, $s, $e];
                if (!empty($f['status']) && isset(OrderService::TRANSITIONS[$f['status']])) {
                    $where .= ' AND o.status = ?';
                    $params[] = $f['status'];
                }
                $rows = $this->db->fetchAll(
                    "SELECT o.number, o.placed_at, o.paid_at, o.channel, o.status, o.customer_name, o.subtotal_cents, o.discount_cents, o.shipping_cents, o.total_cents, u.name AS operator
                     FROM orders o LEFT JOIN users u ON u.id = o.operator_user_id WHERE {$where} ORDER BY o.placed_at LIMIT 5000",
                    $params
                );
                return [
                    'columns' => ['number' => 'Pedido', 'placed_at' => 'Data (local)', 'channel' => 'Canal', 'status' => 'Situação', 'customer_name' => 'Cliente', 'operator' => 'Operador', 'subtotal_cents' => 'Subtotal', 'discount_cents' => 'Desconto', 'shipping_cents' => 'Frete', 'total_cents' => 'Total'],
                    'rows' => $rows,
                    'totals' => ['total_cents' => array_sum(array_map(fn($r) => in_array($r['status'], ['paid', 'picking', 'shipped', 'delivered'], true) ? (int) $r['total_cents'] : 0, $rows))],
                    'note' => 'Pedidos criados no período (data do pedido). O total destacado soma só pedidos com pagamento confirmado.',
                ];
            case 'produtos':
                $rows = $this->db->fetchAll(
                    'SELECT i.sku, i.name, SUM(i.quantity) AS qty, SUM(i.total_cents) AS revenue_cents, SUM(i.unit_cost_cents * i.quantity) AS cost_cents
                     FROM order_items i JOIN orders o ON o.tenant_id = i.tenant_id AND o.id = i.order_id
                     WHERE i.tenant_id = ? AND o.status IN ' . self::PAID . ' AND o.paid_at >= ? AND o.paid_at < ?
                     GROUP BY i.sku, i.name ORDER BY revenue_cents DESC LIMIT 5000',
                    [$t, $s, $e]
                );
                foreach ($rows as &$r) {
                    $r['margin_cents'] = (int) $r['revenue_cents'] - (int) $r['cost_cents'];
                }
                return [
                    'columns' => ['sku' => 'SKU', 'name' => 'Produto', 'qty' => 'Qtd.', 'revenue_cents' => 'Receita', 'cost_cents' => 'Custo', 'margin_cents' => 'Margem bruta'],
                    'rows' => $rows,
                    'totals' => ['qty' => array_sum(array_column($rows, 'qty')), 'revenue_cents' => array_sum(array_column($rows, 'revenue_cents')), 'margin_cents' => array_sum(array_column($rows, 'margin_cents'))],
                    'note' => 'Pedidos com pagamento confirmado no período (data do pagamento). Custo = custo do produto no momento da venda.',
                ];
            case 'pagamentos':
                $rows = $this->db->fetchAll(
                    'SELECT o.number, p.created_at, p.gateway, p.method, p.status, p.amount_cents, p.external_id
                     FROM payments p JOIN orders o ON o.tenant_id = p.tenant_id AND o.id = p.order_id
                     WHERE p.tenant_id = ? AND p.created_at >= ? AND p.created_at < ? ORDER BY p.created_at LIMIT 5000',
                    [$t, $s, $e]
                );
                return [
                    'columns' => ['number' => 'Pedido', 'created_at' => 'Data (local)', 'gateway' => 'Meio', 'method' => 'Forma', 'status' => 'Situação', 'amount_cents' => 'Valor', 'external_id' => 'Id no provedor'],
                    'rows' => $rows,
                    'totals' => ['amount_cents' => array_sum(array_map(fn($r) => $r['status'] === 'approved' ? (int) $r['amount_cents'] : 0, $rows))],
                    'note' => 'Tentativas de pagamento criadas no período. O total soma apenas aprovados.',
                ];
            case 'estoque':
                $rows = $this->db->fetchAll(
                    'SELECT sku, name, stock_qty, reserved_qty, (stock_qty - reserved_qty) AS available, min_stock, cost_cents, (stock_qty * cost_cents) AS value_cents
                     FROM products WHERE tenant_id = ? AND active = 1 ORDER BY name LIMIT 10000',
                    [$t]
                );
                return [
                    'columns' => ['sku' => 'SKU', 'name' => 'Produto', 'stock_qty' => 'Físico', 'reserved_qty' => 'Reservado', 'available' => 'Disponível', 'min_stock' => 'Mínimo', 'cost_cents' => 'Custo unit.', 'value_cents' => 'Valor em estoque'],
                    'rows' => $rows,
                    'totals' => ['value_cents' => array_sum(array_column($rows, 'value_cents'))],
                    'note' => 'Posição atual (não depende do período). Valor = saldo físico × custo cadastrado.',
                ];
            case 'compras':
                $rows = $this->db->fetchAll(
                    "SELECT s.name AS supplier, COUNT(DISTINCT po.id) AS orders, SUM(po.status IN ('received','partial')) AS received_orders,
                            COALESCE(SUM(i.quantity_received * i.unit_cost_cents), 0) AS received_cents,
                            ROUND(AVG(CASE WHEN po.received_at IS NOT NULL AND po.expected_at IS NOT NULL THEN DATEDIFF(po.received_at, po.expected_at) END), 1) AS avg_delay_days
                     FROM purchase_orders po JOIN suppliers s ON s.tenant_id = po.tenant_id AND s.id = po.supplier_id
                     LEFT JOIN purchase_order_items i ON i.tenant_id = po.tenant_id AND i.purchase_order_id = po.id
                     WHERE po.tenant_id = ? AND po.created_at >= ? AND po.created_at < ? AND po.status <> 'cancelled'
                     GROUP BY s.id, s.name ORDER BY received_cents DESC",
                    [$t, $s, $e]
                );
                return [
                    'columns' => ['supplier' => 'Fornecedor', 'orders' => 'Compras', 'received_cents' => 'Valor recebido', 'avg_delay_days' => 'Atraso médio (dias)'],
                    'rows' => $rows,
                    'totals' => ['received_cents' => array_sum(array_column($rows, 'received_cents'))],
                    'note' => 'Compras criadas no período, sem as canceladas. Atraso = recebimento − data prevista (negativo = adiantado).',
                ];
        }
        throw \App\Core\HttpException::notFound('Relatório inexistente.');
    }
}
