<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;

/**
 * Única porta para alterar saldo. Cada operação é um UPDATE condicional atômico
 * (impede vender acima do disponível mesmo com requisições simultâneas) + registro em stock_movements.
 * Disponível = stock_qty − reserved_qty.
 */
final class StockService
{
    public function __construct(private Database $db, private int $tenantId, private ?int $userId = null)
    {
    }

    public function entry(int $productId, int $qty, string $reason, ?int $purchaseOrderId = null): void
    {
        $this->positive($qty);
        $this->apply($productId, 'entry', $qty, 'stock_qty = stock_qty + ?', [$qty], '1=1', [], $reason, null, $purchaseOrderId);
    }

    /** Saída manual (perda, uso interno, venda fora do sistema). Não pode consumir o que está reservado. */
    public function exit(int $productId, int $qty, string $reason): void
    {
        $this->positive($qty);
        $this->apply($productId, 'exit', -$qty, 'stock_qty = stock_qty - ?', [$qty], '(stock_qty - reserved_qty) >= ?', [$qty], $reason,
            fail: 'Saldo disponível insuficiente para esta saída.');
    }

    /** Ajuste por contagem: define o saldo físico. Não pode ficar abaixo do reservado. */
    public function adjust(int $productId, int $countedQty, string $reason): void
    {
        if ($countedQty < 0) {
            throw new BusinessException('A contagem não pode ser negativa.');
        }
        $this->db->transaction(function () use ($productId, $countedQty, $reason) {
            $p = $this->lock($productId);
            if ($countedQty < (int) $p['reserved_qty']) {
                throw new BusinessException("Há {$p['reserved_qty']} unidade(s) reservada(s) em pedidos. A contagem não pode ser menor que isso.");
            }
            $delta = $countedQty - (int) $p['stock_qty'];
            if ($delta === 0) {
                throw new BusinessException('A contagem é igual ao saldo atual; nada a ajustar.');
            }
            $this->apply($productId, 'adjust', $delta, 'stock_qty = ?', [$countedQty], '1=1', [], $reason);
        });
    }

    public function reserve(int $productId, int $qty, int $orderId): void
    {
        $this->positive($qty);
        $this->apply($productId, 'reserve', $qty, 'reserved_qty = reserved_qty + ?', [$qty], '(stock_qty - reserved_qty) >= ?', [$qty],
            'Reserva para o pedido', $orderId, null, 'Estoque insuficiente para um dos itens. Atualize o carrinho.');
    }

    public function release(int $productId, int $qty, int $orderId, string $reason = 'Pedido cancelado: reserva liberada'): void
    {
        $this->positive($qty);
        $this->apply($productId, 'release', -$qty, 'reserved_qty = reserved_qty - ?', [$qty], 'reserved_qty >= ?', [$qty], $reason, $orderId);
    }

    /** Pagamento confirmado: a reserva vira saída definitiva. */
    public function commitReservation(int $productId, int $qty, int $orderId): void
    {
        $this->positive($qty);
        $this->apply($productId, 'exit', -$qty, 'stock_qty = stock_qty - ?, reserved_qty = reserved_qty - ?', [$qty, $qty],
            'reserved_qty >= ? AND stock_qty >= ?', [$qty, $qty], 'Venda: pagamento confirmado', $orderId);
    }

    public function returnToStock(int $productId, int $qty, string $reason, ?int $orderId = null): void
    {
        $this->positive($qty);
        $this->apply($productId, 'return', $qty, 'stock_qty = stock_qty + ?', [$qty], '1=1', [], $reason, $orderId);
    }

    public function history(array $f, int $page, int $perPage = 30): array
    {
        $where = ['m.tenant_id = ?'];
        $params = [$this->tenantId];
        if (!empty($f['product_id'])) {
            $where[] = 'm.product_id = ?';
            $params[] = (int) $f['product_id'];
        }
        if (!empty($f['type'])) {
            $where[] = 'm.type = ?';
            $params[] = (string) $f['type'];
        }
        $from = 'FROM stock_movements m JOIN products p ON p.tenant_id = m.tenant_id AND p.id = m.product_id
                 LEFT JOIN users u ON u.id = m.user_id WHERE ' . implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) {$from}", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $items = $this->db->fetchAll(
            "SELECT m.*, p.sku, p.name AS product_name, u.name AS user_name {$from} ORDER BY m.id DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage]
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private function positive(int $qty): void
    {
        if ($qty <= 0) {
            throw new BusinessException('A quantidade deve ser maior que zero.');
        }
    }

    private function lock(int $productId): array
    {
        $p = $this->db->fetch('SELECT * FROM products WHERE tenant_id = ? AND id = ? FOR UPDATE', [$this->tenantId, $productId]);
        if (!$p) {
            throw HttpException::notFound('Produto não encontrado.');
        }
        return $p;
    }

    private function apply(
        int $productId,
        string $type,
        int $delta,
        string $set,
        array $setParams,
        string $condition,
        array $conditionParams,
        string $reason,
        ?int $orderId = null,
        ?int $purchaseOrderId = null,
        string $fail = 'Operação de estoque não permitida.'
    ): void {
        $reason = trim($reason);
        if ($reason === '') {
            throw new BusinessException('Informe o motivo da movimentação.');
        }
        $this->db->transaction(function () use ($productId, $type, $delta, $set, $setParams, $condition, $conditionParams, $reason, $orderId, $purchaseOrderId, $fail) {
            $this->lock($productId);
            $n = $this->db->execute(
                "UPDATE products SET {$set}, updated_at = ? WHERE tenant_id = ? AND id = ? AND ({$condition})",
                [...$setParams, Clock::now(), $this->tenantId, $productId, ...$conditionParams]
            );
            if ($n !== 1) {
                throw new BusinessException($fail);
            }
            $after = $this->db->fetch('SELECT stock_qty, reserved_qty FROM products WHERE tenant_id = ? AND id = ?', [$this->tenantId, $productId]);
            $this->db->insert('stock_movements', [
                'tenant_id' => $this->tenantId,
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => (int) $after['stock_qty'],
                'reserved_after' => (int) $after['reserved_qty'],
                'reason' => mb_substr($reason, 0, 255),
                'user_id' => $this->userId,
                'order_id' => $orderId,
                'purchase_order_id' => $purchaseOrderId,
                'created_at' => Clock::now(),
            ]);
        });
    }
}
