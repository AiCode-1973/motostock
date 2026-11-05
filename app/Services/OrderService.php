<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Support\Counter;
use App\Support\Labels;

/**
 * Pedidos da loja virtual e do balcão: criação com reserva de estoque, máquina de estados e histórico.
 * Preços SEMPRE vêm do cadastro do produto, nunca do navegador.
 */
final class OrderService
{
    public const TRANSITIONS = [
        'awaiting_payment' => ['paid', 'cancelled'],
        'paid' => ['picking', 'shipped', 'cancelled', 'refunded'],
        'picking' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];
    public const PAID_STATES = ['paid', 'picking', 'shipped', 'delivered'];
    private const GOODS_IN_HOUSE = ['paid', 'picking'];

    private StockService $stock;

    public function __construct(
        private Database $db,
        private int $tenantId,
        private ?int $userId = null,
        private string $actor = 'staff',
    ) {
        $this->stock = new StockService($db, $tenantId, $userId);
    }

    /** @return array{id: int, number: int, token: string, total_cents: int} */
    public function create(array $in): array
    {
        $channel = $in['channel'] ?? 'store';
        if (!in_array($channel, ['store', 'manual'], true)) {
            throw new \InvalidArgumentException('Canal inválido');
        }
        $lines = [];
        foreach ($in['items'] ?? [] as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($lines[$pid])) {
                $lines[$pid] = ['quantity' => 0, 'fitment_note' => null];
            }
            $lines[$pid]['quantity'] += $qty;
            if (!empty($item['fitment_note'])) {
                $lines[$pid]['fitment_note'] = mb_substr(trim((string) $item['fitment_note']), 0, 160);
            }
        }
        if (!$lines) {
            throw new BusinessException('O pedido precisa de pelo menos um item.');
        }

        return $this->db->transaction(function () use ($in, $channel, $lines) {
            (new PlanLimitService($this->db, $this->tenantId))->assertCanAdd('orders');

            $items = [];
            $subtotal = 0;
            foreach ($lines as $pid => $line) {
                if ($line['quantity'] > 999) {
                    throw new BusinessException('Quantidade máxima por item: 999.');
                }
                $p = $this->db->fetch('SELECT * FROM products WHERE tenant_id = ? AND id = ? AND active = 1 FOR UPDATE', [$this->tenantId, $pid]);
                if (!$p) {
                    throw new BusinessException('Um dos produtos não está mais disponível. Revise o carrinho.');
                }
                $available = (int) $p['stock_qty'] - (int) $p['reserved_qty'];
                if ($line['quantity'] > $available) {
                    throw new BusinessException(sprintf('Estoque insuficiente para "%s": disponível %d.', $p['name'], max(0, $available)));
                }
                $total = (int) $p['price_cents'] * $line['quantity'];
                $subtotal += $total;
                $items[] = [
                    'product_id' => $pid,
                    'sku' => $p['sku'],
                    'name' => $p['name'],
                    'unit_price_cents' => (int) $p['price_cents'],
                    'unit_cost_cents' => (int) $p['cost_cents'],
                    'quantity' => $line['quantity'],
                    'total_cents' => $total,
                    'fitment_note' => $line['fitment_note'],
                ];
            }

            // Cliente
            $customerId = null;
            $snapshot = $in['customer'] ?? [];
            if (!empty($in['customer_id'])) {
                $c = $this->db->fetch('SELECT * FROM customers WHERE tenant_id = ? AND id = ? AND anonymized_at IS NULL', [$this->tenantId, (int) $in['customer_id']]);
                if (!$c) {
                    throw HttpException::notFound('Cliente não encontrado.');
                }
                $customerId = (int) $c['id'];
                $snapshot = ['name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone']];
            }
            if (trim((string) ($snapshot['name'] ?? '')) === '') {
                $snapshot['name'] = $channel === 'manual' ? 'Consumidor (balcão)' : throw new BusinessException('Informe o nome do cliente.');
            }

            // Entrega
            $shipping = 0;
            $method = null;
            $address = $in['address'] ?? [];
            if (!empty($in['shipping_method_id'])) {
                $method = $this->db->fetch('SELECT * FROM shipping_methods WHERE tenant_id = ? AND id = ? AND active = 1', [$this->tenantId, (int) $in['shipping_method_id']]);
                if (!$method) {
                    throw new BusinessException('Escolha uma forma de entrega válida.');
                }
                $shipping = self::shippingCost($method, $subtotal);
                if ($method['kind'] === 'delivery') {
                    foreach (['zip', 'street', 'number', 'city', 'state'] as $f) {
                        if (trim((string) ($address[$f] ?? '')) === '') {
                            throw new BusinessException('Preencha o endereço de entrega completo.');
                        }
                    }
                    if (!self::methodServes($method, (string) $address['state'])) {
                        throw new BusinessException('Esta forma de entrega não atende a UF informada.');
                    }
                } else {
                    $address = [];
                }
            } elseif ($channel === 'store') {
                throw new BusinessException('Escolha uma forma de entrega.');
            }

            // Desconto: somente no balcão e limitado.
            $discount = 0;
            if ($channel === 'manual' && !empty($in['discount_cents'])) {
                $discount = max(0, (int) $in['discount_cents']);
                $limitPct = $in['discount_limit_percent'] ?? null;
                if ($limitPct !== null && $discount > intdiv($subtotal * (int) $limitPct, 100)) {
                    throw new BusinessException("Seu perfil permite desconto de até {$limitPct}%.");
                }
                if ($discount > $subtotal) {
                    throw new BusinessException('O desconto não pode ser maior que o subtotal.');
                }
            }

            $number = Counter::next($this->db, $this->tenantId, 'order');
            $token = bin2hex(random_bytes(20));
            $now = Clock::now();
            $orderId = $this->db->insert('orders', [
                'tenant_id' => $this->tenantId,
                'number' => $number,
                'customer_id' => $customerId,
                'channel' => $channel,
                'status' => 'awaiting_payment',
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'shipping_cents' => $shipping,
                'total_cents' => $subtotal - $discount + $shipping,
                'payment_method' => $in['payment_method'] ?? null,
                'shipping_method_id' => $method ? (int) $method['id'] : null,
                'shipping_name' => $method['name'] ?? null,
                'shipping_days' => $method ? self::daysLabel($method) : null,
                'customer_name' => mb_substr(trim((string) $snapshot['name']), 0, 120),
                'customer_email' => $snapshot['email'] ?? null,
                'customer_phone' => $snapshot['phone'] ?? null,
                'ship_zip' => $address['zip'] ?? null,
                'ship_street' => $address['street'] ?? null,
                'ship_number' => $address['number'] ?? null,
                'ship_complement' => $address['complement'] ?? null,
                'ship_district' => $address['district'] ?? null,
                'ship_city' => $address['city'] ?? null,
                'ship_state' => $address['state'] ?? null,
                'notes' => $in['notes'] ?? null,
                'tracking_token_hash' => hash('sha256', $token),
                'operator_user_id' => $channel === 'manual' ? $this->userId : null,
                'privacy_version' => $in['privacy_version'] ?? null,
                'placed_at' => $now,
                'expires_at' => $channel === 'store' ? Clock::now('+' . Env::int('ORDER_PAYMENT_TTL_HOURS', 48) . ' hours') : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($items as $it) {
                $this->db->insert('order_items', ['tenant_id' => $this->tenantId, 'order_id' => $orderId] + $it);
                $this->stock->reserve($it['product_id'], $it['quantity'], $orderId);
            }
            $this->history($orderId, null, 'awaiting_payment', $channel === 'manual' ? 'Pedido criado no balcão' : 'Pedido feito na loja virtual');
            return ['id' => $orderId, 'number' => $number, 'token' => $token, 'total_cents' => $subtotal - $discount + $shipping];
        });
    }

    public function transition(int $orderId, string $to, array $opts = []): void
    {
        $this->db->transaction(function () use ($orderId, $to, $opts) {
            $order = $this->lock($orderId);
            $from = $order['status'];
            if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                throw new BusinessException(sprintf('Não é possível passar de "%s" para "%s".', Labels::get('order', $from), Labels::get('order', $to)));
            }
            $items = $this->items($orderId);
            $set = ['status' => $to, 'updated_at' => Clock::now()];

            if ($to === 'paid') {
                foreach ($items as $it) {
                    $this->stock->commitReservation((int) $it['product_id'], (int) $it['quantity'], $orderId);
                }
                $set['paid_at'] = Clock::now();
                $set['expires_at'] = null;
            } elseif ($to === 'cancelled') {
                foreach ($items as $it) {
                    if ($from === 'awaiting_payment') {
                        $this->stock->release((int) $it['product_id'], (int) $it['quantity'], $orderId);
                    } else {
                        $this->stock->returnToStock((int) $it['product_id'], (int) $it['quantity'], 'Pedido cancelado após pagamento: devolvido ao estoque', $orderId);
                    }
                }
                $set['cancelled_at'] = Clock::now();
            } elseif ($to === 'refunded') {
                $restock = in_array($from, self::GOODS_IN_HOUSE, true) || !empty($opts['restock']);
                if ($restock) {
                    foreach ($items as $it) {
                        $this->stock->returnToStock((int) $it['product_id'], (int) $it['quantity'], 'Reembolso: mercadoria de volta ao estoque', $orderId);
                    }
                }
            }
            $cols = implode(', ', array_map(fn($c) => "{$c} = ?", array_keys($set)));
            $this->db->execute("UPDATE orders SET {$cols} WHERE tenant_id = ? AND id = ?", [...array_values($set), $this->tenantId, $orderId]);
            $this->history($orderId, $from, $to, $opts['note'] ?? null);
        });
    }

    /**
     * Chamado após confirmação do provedor (webhook validado + consulta à API).
     * Idempotente: se já estiver pago, não faz nada. Se o pedido já tiver sido cancelado, registra para análise.
     */
    public function markPaidByGateway(int $orderId, string $note): bool
    {
        return $this->db->transaction(function () use ($orderId, $note) {
            $order = $this->lock($orderId);
            if ($order['status'] === 'awaiting_payment') {
                $this->transition($orderId, 'paid', ['note' => $note]);
                return true;
            }
            if (in_array($order['status'], ['cancelled', 'refunded'], true)) {
                $this->history($orderId, $order['status'], $order['status'], 'ATENÇÃO: pagamento aprovado com o pedido já ' . Labels::get('order', $order['status']) . '. Avalie estorno no provedor.');
            }
            return false;
        });
    }

    /** Cancela pedidos da loja não pagos dentro do prazo e libera a reserva. */
    public function expireOverdue(): int
    {
        $ids = $this->db->fetchAll(
            "SELECT id FROM orders WHERE tenant_id = ? AND status = 'awaiting_payment' AND expires_at IS NOT NULL AND expires_at < ?",
            [$this->tenantId, Clock::now()]
        );
        foreach ($ids as $row) {
            $this->transition((int) $row['id'], 'cancelled', ['note' => 'Prazo de pagamento expirado']);
        }
        return count($ids);
    }

    public function setTracking(int $orderId, string $code): void
    {
        $this->lock($orderId);
        $this->db->execute('UPDATE orders SET tracking_code = ?, updated_at = ? WHERE tenant_id = ? AND id = ?', [mb_substr($code, 0, 60), Clock::now(), $this->tenantId, $orderId]);
        $this->history($orderId, null, $this->lock($orderId)['status'], 'Código de rastreio: ' . $code);
    }

    public function items(int $orderId): array
    {
        return $this->db->fetchAll('SELECT * FROM order_items WHERE tenant_id = ? AND order_id = ? ORDER BY id', [$this->tenantId, $orderId]);
    }

    public function historyOf(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT h.*, u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id = h.user_id
             WHERE h.tenant_id = ? AND h.order_id = ? ORDER BY h.id',
            [$this->tenantId, $orderId]
        );
    }

    public function findByNumberAndToken(int $number, string $token): ?array
    {
        $o = $this->db->fetch('SELECT * FROM orders WHERE tenant_id = ? AND number = ?', [$this->tenantId, $number]);
        if (!$o || !$o['tracking_token_hash'] || !hash_equals($o['tracking_token_hash'], hash('sha256', $token))) {
            return null;
        }
        return $o;
    }

    public static function shippingCost(array $method, int $subtotal): int
    {
        if ($method['free_above_cents'] !== null && $subtotal >= (int) $method['free_above_cents']) {
            return 0;
        }
        return (int) $method['price_cents'];
    }

    public static function methodServes(array $method, string $uf): bool
    {
        $regions = trim((string) $method['regions']);
        if ($regions === '') {
            return true;
        }
        return in_array(strtoupper($uf), array_map('trim', explode(',', strtoupper($regions))), true);
    }

    public static function daysLabel(array $m): string
    {
        if ($m['kind'] === 'pickup' && (int) $m['days_max'] === 0) {
            return 'Retirada imediata após confirmação';
        }
        return (int) $m['days_min'] === (int) $m['days_max']
            ? "{$m['days_max']} dia(s) útil(eis)"
            : "{$m['days_min']} a {$m['days_max']} dias úteis";
    }

    private function lock(int $orderId): array
    {
        return $this->db->fetch('SELECT * FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE', [$this->tenantId, $orderId])
            ?? throw HttpException::notFound('Pedido não encontrado.');
    }

    private function history(int $orderId, ?string $from, string $to, ?string $note): void
    {
        $this->db->insert('order_status_history', [
            'tenant_id' => $this->tenantId,
            'order_id' => $orderId,
            'from_status' => $from,
            'to_status' => $to,
            'actor' => $this->actor,
            'user_id' => $this->userId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'created_at' => Clock::now(),
        ]);
    }
}
