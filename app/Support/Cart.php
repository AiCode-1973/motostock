<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\Session;
use App\Core\Tenant;

/** Carrinho na sessão, separado por loja. Preço e disponibilidade são sempre relidos do banco. */
final class Cart
{
    private static function key(): string
    {
        return 'cart.' . Tenant::id();
    }

    private static function raw(): array
    {
        $c = Session::get(self::key(), []);
        return is_array($c) ? $c : [];
    }

    public static function count(): int
    {
        return array_sum(array_map(fn($l) => (int) $l['qty'], self::raw()));
    }

    public static function add(int $productId, int $qty, ?string $fitmentNote): void
    {
        $p = Database::connect()->fetch('SELECT id, stock_qty, reserved_qty, name FROM products WHERE tenant_id = ? AND id = ? AND active = 1', [Tenant::id(), $productId]);
        if (!$p) {
            throw new BusinessException('Produto indisponível.');
        }
        $cart = self::raw();
        $newQty = min(999, max(1, $qty) + (int) ($cart[$productId]['qty'] ?? 0));
        $available = (int) $p['stock_qty'] - (int) $p['reserved_qty'];
        if ($newQty > $available) {
            throw new BusinessException($available > 0 ? "Só temos {$available} unidade(s) de \"{$p['name']}\"." : "\"{$p['name']}\" está sem estoque no momento.");
        }
        if (count($cart) >= 50 && !isset($cart[$productId])) {
            throw new BusinessException('O carrinho aceita até 50 produtos diferentes.');
        }
        $cart[$productId] = [
            'qty' => $newQty,
            'fitment' => $fitmentNote !== null && trim($fitmentNote) !== '' ? mb_substr(trim($fitmentNote), 0, 160) : ($cart[$productId]['fitment'] ?? null),
        ];
        Session::set(self::key(), $cart);
    }

    public static function setQuantity(int $productId, int $qty): void
    {
        $cart = self::raw();
        if (!isset($cart[$productId])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId]['qty'] = min(999, $qty);
        }
        Session::set(self::key(), $cart);
    }

    public static function clear(): void
    {
        Session::remove(self::key());
    }

    /** @return array{lines: array, subtotal: int, issues: array} */
    public static function details(): array
    {
        $cart = self::raw();
        if (!$cart) {
            return ['lines' => [], 'subtotal' => 0, 'issues' => []];
        }
        $ids = array_map('intval', array_keys($cart));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::connect()->fetchAll(
            "SELECT p.id, p.sku, p.name, p.slug, p.price_cents, (p.stock_qty - p.reserved_qty) AS available,
                    (SELECT i.id FROM product_images i WHERE i.tenant_id = p.tenant_id AND i.product_id = p.id ORDER BY i.position, i.id LIMIT 1) AS image_id,
                    (SELECT COUNT(*) FROM product_fitments f WHERE f.tenant_id = p.tenant_id AND f.product_id = p.id) AS fitments
             FROM products p WHERE p.tenant_id = ? AND p.active = 1 AND p.id IN ({$in})",
            [Tenant::id(), ...$ids]
        );
        $byId = array_column($rows, null, 'id');
        $lines = [];
        $issues = [];
        $subtotal = 0;
        foreach ($cart as $pid => $line) {
            $p = $byId[$pid] ?? null;
            if (!$p) {
                $issues[] = 'Um produto saiu de catálogo e foi removido do carrinho.';
                unset($cart[$pid]);
                continue;
            }
            $qty = (int) $line['qty'];
            if ($qty > (int) $p['available']) {
                $issues[] = (int) $p['available'] > 0
                    ? "A quantidade de \"{$p['name']}\" foi ajustada para {$p['available']} (estoque disponível)."
                    : "\"{$p['name']}\" ficou sem estoque e foi removido.";
                $qty = max(0, (int) $p['available']);
                if ($qty === 0) {
                    unset($cart[$pid]);
                    continue;
                }
                $cart[$pid]['qty'] = $qty;
            }
            $total = $qty * (int) $p['price_cents'];
            $subtotal += $total;
            $lines[] = $p + ['qty' => $qty, 'total' => $total, 'fitment' => $line['fitment'] ?? null];
        }
        Session::set(self::key(), $cart);
        return ['lines' => $lines, 'subtotal' => $subtotal, 'issues' => $issues];
    }
}
