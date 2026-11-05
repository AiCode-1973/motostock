<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Services\OrderService;
use App\Support\Cart;

final class CartController extends StoreController
{
    public function show(Request $r): Response
    {
        $cart = Cart::details();
        $methods = $this->db()->fetchAll('SELECT * FROM shipping_methods WHERE tenant_id = ? AND active = 1 ORDER BY kind DESC, price_cents', [Tenant::id()]);
        foreach ($methods as &$m) {
            $m['cost'] = OrderService::shippingCost($m, $cart['subtotal']);
            $m['days'] = OrderService::daysLabel($m);
        }
        return $this->view('store/cart', ['title' => 'Carrinho', 'cart' => $cart, 'methods' => $methods]);
    }

    public function add(Request $r): Response
    {
        Cart::add($r->int('product_id'), max(1, $r->int('quantity', 1)), $r->str('fitment_note') ?: null);
        $this->flash('Produto adicionado ao carrinho.');
        return $this->toStore('/carrinho');
    }

    public function update(Request $r): Response
    {
        foreach ((array) ($r->post['qty'] ?? []) as $pid => $qty) {
            Cart::setQuantity((int) $pid, (int) $qty);
        }
        if ($r->int('remove')) {
            Cart::setQuantity($r->int('remove'), 0);
        }
        $this->flash('Carrinho atualizado.');
        return $this->toStore('/carrinho');
    }
}
