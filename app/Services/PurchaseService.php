<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Money;
use App\Support\Counter;

/** Pedidos de compra a fornecedores e recebimento de mercadoria (gera entradas de estoque). */
final class PurchaseService
{
    public function __construct(private Database $db, private int $tenantId, private ?int $userId = null)
    {
    }

    /** $items: [['product_id' => , 'quantity' => , 'unit_cost' => '12,50'], ...] */
    public function create(int $supplierId, array $items, ?string $expectedAt, ?string $notes): int
    {
        if (!$this->db->value('SELECT 1 FROM suppliers WHERE tenant_id = ? AND id = ?', [$this->tenantId, $supplierId])) {
            throw new BusinessException('Escolha um fornecedor válido.');
        }
        if ($expectedAt !== null && $expectedAt !== '' && !\DateTime::createFromFormat('Y-m-d', $expectedAt)) {
            throw new BusinessException('Data prevista inválida.');
        }
        $lines = [];
        foreach ($items as $it) {
            $pid = (int) ($it['product_id'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($pid <= 0 && $qty <= 0) {
                continue;
            }
            $cost = Money::parse((string) ($it['unit_cost'] ?? ''));
            if ($pid <= 0 || $qty <= 0 || $cost === null) {
                throw new BusinessException('Cada item precisa de produto, quantidade e custo unitário.');
            }
            if (!$this->db->value('SELECT 1 FROM products WHERE tenant_id = ? AND id = ?', [$this->tenantId, $pid])) {
                throw new BusinessException('Produto inválido na compra.');
            }
            $lines[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_cost_cents' => $cost];
        }
        if (!$lines) {
            throw new BusinessException('Adicione pelo menos um item.');
        }
        return $this->db->transaction(function () use ($supplierId, $lines, $expectedAt, $notes) {
            $total = array_sum(array_map(fn($l) => $l['quantity'] * $l['unit_cost_cents'], $lines));
            $id = $this->db->insert('purchase_orders', [
                'tenant_id' => $this->tenantId, 'number' => Counter::next($this->db, $this->tenantId, 'purchase'),
                'supplier_id' => $supplierId, 'status' => 'draft', 'expected_at' => $expectedAt ?: null,
                'notes' => $notes ?: null, 'total_cents' => $total, 'created_by' => $this->userId,
                'created_at' => Clock::now(), 'updated_at' => Clock::now(),
            ]);
            foreach ($lines as $l) {
                $this->db->insert('purchase_order_items', ['tenant_id' => $this->tenantId, 'purchase_order_id' => $id] + $l);
            }
            Audit::log('panel', 'purchase.created', $this->tenantId, $this->userId, 'purchase_order', $id);
            return $id;
        });
    }

    public function markOrdered(int $id): void
    {
        $po = $this->lock($id);
        if ($po['status'] !== 'draft') {
            throw new BusinessException('Só rascunhos podem ser enviados ao fornecedor.');
        }
        $this->setStatus($id, 'ordered');
    }

    public function cancel(int $id): void
    {
        $po = $this->lock($id);
        if (!in_array($po['status'], ['draft', 'ordered'], true)) {
            throw new BusinessException('Compras com mercadoria recebida não podem ser canceladas.');
        }
        $this->setStatus($id, 'cancelled');
    }

    /**
     * Recebe quantidades (total ou parcial). $received: [itemId => qty].
     * Gera entrada de estoque ligada à compra e atualiza o custo do produto.
     */
    public function receive(int $id, array $received): void
    {
        $this->db->transaction(function () use ($id, $received) {
            $po = $this->lock($id);
            if (!in_array($po['status'], ['ordered', 'partial'], true)) {
                throw new BusinessException('Envie a compra ao fornecedor antes de registrar o recebimento.');
            }
            $stock = new StockService($this->db, $this->tenantId, $this->userId);
            $any = false;
            foreach ($this->items($id) as $it) {
                $qty = (int) ($received[$it['id']] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $pending = (int) $it['quantity'] - (int) $it['quantity_received'];
                if ($qty > $pending) {
                    throw new BusinessException("Quantidade recebida de {$it['name']} maior que a pendente ({$pending}).");
                }
                $this->db->execute('UPDATE purchase_order_items SET quantity_received = quantity_received + ? WHERE tenant_id = ? AND id = ?', [$qty, $this->tenantId, $it['id']]);
                $stock->entry((int) $it['product_id'], $qty, 'Recebimento da compra nº ' . $po['number'], $id);
                $this->db->execute('UPDATE products SET cost_cents = ?, updated_at = ? WHERE tenant_id = ? AND id = ?', [$it['unit_cost_cents'], Clock::now(), $this->tenantId, $it['product_id']]);
                $any = true;
            }
            if (!$any) {
                throw new BusinessException('Informe a quantidade recebida de pelo menos um item.');
            }
            $pendingTotal = (int) $this->db->value('SELECT SUM(quantity - quantity_received) FROM purchase_order_items WHERE tenant_id = ? AND purchase_order_id = ?', [$this->tenantId, $id]);
            $this->setStatus($id, $pendingTotal === 0 ? 'received' : 'partial');
            if ($pendingTotal === 0) {
                $this->db->execute('UPDATE purchase_orders SET received_at = ? WHERE tenant_id = ? AND id = ?', [Clock::now(), $this->tenantId, $id]);
            }
        });
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            'SELECT i.*, p.sku, p.name FROM purchase_order_items i JOIN products p ON p.tenant_id = i.tenant_id AND p.id = i.product_id
             WHERE i.tenant_id = ? AND i.purchase_order_id = ? ORDER BY i.id',
            [$this->tenantId, $id]
        );
    }

    private function lock(int $id): array
    {
        return $this->db->fetch('SELECT * FROM purchase_orders WHERE tenant_id = ? AND id = ? FOR UPDATE', [$this->tenantId, $id])
            ?? throw HttpException::notFound('Compra não encontrada.');
    }

    private function setStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE purchase_orders SET status = ?, updated_at = ? WHERE tenant_id = ? AND id = ?', [$status, Clock::now(), $this->tenantId, $id]);
        Audit::log('panel', 'purchase.' . $status, $this->tenantId, $this->userId, 'purchase_order', $id);
    }
}
