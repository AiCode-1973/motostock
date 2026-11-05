<?php
declare(strict_types=1);

namespace Tests;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Services\OrderService;
use App\Services\PaymentService;

final class OrderFlowTest extends TestCase
{
    private array $t;
    private int $tid;
    private int $pid;
    private OrderService $orders;

    public function setUp(): void
    {
        parent::setUp();
        $this->t = $this->tenant('pedidos');
        $this->tid = (int) $this->t['tenant']['id'];
        $this->pid = $this->product($this->tid, 'PED-1', 12990, 10);
        $this->orders = new OrderService($this->db, $this->tid, (int) $this->t['owner']['id']);
    }

    private function order(int $qty = 2): array
    {
        return $this->orders->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => $qty]]]);
    }

    private function status(int $id): string
    {
        return (string) $this->db->value('SELECT status FROM orders WHERE id = ?', [$id]);
    }

    public function testPriceAlwaysComesFromCatalog(): void
    {
        $o = $this->orders->create(['channel' => 'manual', 'items' => [['product_id' => $this->pid, 'quantity' => 2, 'unit_price_cents' => 1, 'price' => 1]], 'total_cents' => 1]);
        $this->assertSame(25980, $o['total_cents']);
        $this->assertSame(12990, (int) $this->db->value('SELECT unit_price_cents FROM order_items WHERE order_id = ?', [$o['id']]));
    }

    public function testFullHappyPathUpdatesStockAndHistory(): void
    {
        $o = $this->order(2);
        $this->assertSame(['stock_qty' => 10, 'reserved_qty' => 2], $this->stock($this->tid, $this->pid));
        (new PaymentService($this->db, $this->t['tenant']))->recordManual($o['id'], 'cash', (int) $this->t['owner']['id'], null);
        $this->assertSame(['stock_qty' => 8, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
        foreach (['picking', 'shipped', 'delivered'] as $s) {
            $this->orders->transition($o['id'], $s);
        }
        $this->assertSame('delivered', $this->status($o['id']));
        $hist = array_column($this->db->fetchAll('SELECT to_status FROM order_status_history WHERE order_id = ? ORDER BY id', [$o['id']]), 'to_status');
        $this->assertSame(['awaiting_payment', 'paid', 'picking', 'shipped', 'delivered'], $hist);
        $this->assertSame((int) $this->t['owner']['id'], (int) $this->db->value('SELECT user_id FROM order_status_history WHERE order_id = ? AND to_status = ?', [$o['id'], 'shipped']));
    }

    public function testInvalidTransitionsAreRejected(): void
    {
        $o = $this->order();
        $this->assertThrows(BusinessException::class, fn() => $this->orders->transition($o['id'], 'shipped'), 'Não é possível');
        $this->orders->transition($o['id'], 'cancelled');
        $this->assertThrows(BusinessException::class, fn() => $this->orders->transition($o['id'], 'paid'));
    }

    public function testCancelBeforePaymentReleasesReservation(): void
    {
        $o = $this->order(3);
        $this->orders->transition($o['id'], 'cancelled', ['note' => 'cliente desistiu']);
        $this->assertSame(['stock_qty' => 10, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
        $this->assertSame('release', $this->db->value('SELECT type FROM stock_movements ORDER BY id DESC LIMIT 1'));
    }

    public function testCancelAfterPaymentReturnsGoods(): void
    {
        $o = $this->order(3);
        (new PaymentService($this->db, $this->t['tenant']))->recordManual($o['id'], 'pix_manual', (int) $this->t['owner']['id'], null);
        $this->orders->transition($o['id'], 'cancelled');
        $this->assertSame(['stock_qty' => 10, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
    }

    public function testRefundAfterDeliveryRestocksOnlyWhenAsked(): void
    {
        $pay = new PaymentService($this->db, $this->t['tenant']);
        $o1 = $this->order(1);
        $pay->recordManual($o1['id'], 'cash', 1, null);
        $this->orders->transition($o1['id'], 'shipped');
        $this->orders->transition($o1['id'], 'delivered');
        $this->orders->transition($o1['id'], 'refunded');
        $this->assertSame(9, $this->stock($this->tid, $this->pid)['stock_qty'], 'sem devolução física');

        $o2 = $this->order(1);
        $pay->recordManual($o2['id'], 'cash', 1, null);
        $this->orders->transition($o2['id'], 'shipped');
        $this->orders->transition($o2['id'], 'refunded', ['restock' => true]);
        $this->assertSame(9, $this->stock($this->tid, $this->pid)['stock_qty'], 'peça voltou ao estoque');
    }

    public function testUnpaidStoreOrdersExpireAndReleaseStock(): void
    {
        $o = $this->orders->create(['channel' => 'store', 'customer' => ['name' => 'Cliente'], 'shipping_method_id' => $this->shippingPickup($this->tid),
            'items' => [['product_id' => $this->pid, 'quantity' => 4]]]);
        $this->assertSame(0, $this->orders->expireOverdue());
        Clock::$fixed = time() + 49 * 3600;
        $this->assertSame(1, $this->orders->expireOverdue());
        $this->assertSame('cancelled', $this->status($o['id']));
        $this->assertSame(0, $this->stock($this->tid, $this->pid)['reserved_qty']);
    }

    public function testStoreOrderRequiresShippingAndAddressForDelivery(): void
    {
        $delivery = (int) $this->db->value("SELECT id FROM shipping_methods WHERE tenant_id = ? AND kind = 'delivery'", [$this->tid]);
        $this->db->execute('UPDATE shipping_methods SET active = 1, price_cents = 1500, free_above_cents = 30000, regions = ? WHERE id = ?', ['SP,RJ', $delivery]);
        $base = ['channel' => 'store', 'customer' => ['name' => 'Cliente'], 'items' => [['product_id' => $this->pid, 'quantity' => 1]]];
        $this->assertThrows(BusinessException::class, fn() => $this->orders->create($base), 'forma de entrega');
        $this->assertThrows(BusinessException::class, fn() => $this->orders->create($base + ['shipping_method_id' => $delivery]), 'endereço');
        $addr = ['zip' => '01001000', 'street' => 'Rua A', 'number' => '1', 'city' => 'São Paulo', 'state' => 'MG'];
        $this->assertThrows(BusinessException::class, fn() => $this->orders->create($base + ['shipping_method_id' => $delivery, 'address' => $addr]), 'UF');
        $addr['state'] = 'SP';
        $o = $this->orders->create($base + ['shipping_method_id' => $delivery, 'address' => $addr]);
        $this->assertSame(12990 + 1500, $o['total_cents']);
        $o2 = $this->orders->create(['items' => [['product_id' => $this->pid, 'quantity' => 3]]] + $base + ['shipping_method_id' => $delivery, 'address' => $addr]);
        $this->assertSame(3 * 12990, $o2['total_cents'], 'frete grátis acima de R$ 300');
    }

    public function testOrderNumbersAreSequentialPerTenant(): void
    {
        $other = $this->tenant('outra');
        $pOther = $this->product((int) $other['tenant']['id'], 'OUT-1');
        $this->assertSame(1, $this->order(1)['number']);
        $this->assertSame(2, $this->order(1)['number']);
        $this->assertSame(1, (new OrderService($this->db, (int) $other['tenant']['id']))->create(['channel' => 'manual', 'items' => [['product_id' => $pOther, 'quantity' => 1]]])['number']);
    }

    public function testPlanOrderLimitBlocksNewOrdersWithClearMessage(): void
    {
        $limited = $this->tenant('limitada', $this->plan(['max_orders_month' => 1, 'max_products' => 1]));
        $lt = (int) $limited['tenant']['id'];
        $p = $this->product($lt, 'LIM-1');
        $this->assertThrows(BusinessException::class, fn() => $this->product($lt, 'LIM-2'), 'até 1 produtos');
        $s = new OrderService($this->db, $lt);
        $s->create(['channel' => 'manual', 'items' => [['product_id' => $p, 'quantity' => 1]]]);
        $this->assertThrows(BusinessException::class, fn() => $s->create(['channel' => 'manual', 'items' => [['product_id' => $p, 'quantity' => 1]]]), 'Limite do plano');
    }

    public function testCheckoutThroughStorefront(): void
    {
        $br = $this->browser();
        $slug = $this->db->value('SELECT slug FROM products WHERE id = ?', [$this->pid]);
        $this->assertStatus(200, $br->get('/loja/pedidos/produto/' . $slug));
        $this->assertStatus(303, $br->post('/loja/pedidos/carrinho/adicionar', ['product_id' => $this->pid, 'quantity' => 2, 'fitment_note' => 'Honda CG 160 2020']));
        $cart = $br->get('/loja/pedidos/carrinho');
        $this->assertContains('Honda CG 160 2020', $cart->body);
        $this->assertStatus(200, $br->get('/loja/pedidos/checkout'));
        $data = ['name' => 'Cliente Teste', 'email' => 'cliente@teste.test', 'phone' => '(11) 98888-7777', 'shipping_method_id' => $this->shippingPickup($this->tid), 'payment' => 'offline'];
        $r = $br->post('/loja/pedidos/checkout', $data, true, ['referer' => 'http://localhost/loja/pedidos/checkout']);
        $this->assertRedirectTo('/loja/pedidos/checkout', $r); // falta aceitar a política
        $r = $br->post('/loja/pedidos/checkout', $data + ['accept_privacy' => '1']);
        $this->assertStatus(303, $r);
        $location = (string) $r->headers['Location'];
        $this->assertContains('/loja/pedidos/pedido/1/', $location);
        $order = $this->db->fetch('SELECT * FROM orders WHERE number = 1 AND tenant_id = ?', [$this->tid]);
        $this->assertSame('store', $order['channel']);
        $this->assertSame(25980, (int) $order['total_cents']);
        $this->assertSame('Honda CG 160 2020', $this->db->value('SELECT fitment_note FROM order_items WHERE order_id = ?', [$order['id']]));
        $this->assertSame(2, $this->stock($this->tid, $this->pid)['reserved_qty']);
        $this->assertSame(1, (int) $this->db->value('SELECT privacy_version FROM orders WHERE id = ?', [$order['id']]));
        // Acompanhamento só com o token certo
        $this->assertStatus(200, $br->get(parse_url($location, PHP_URL_PATH)));
        $this->assertStatus(404, $this->browser()->get('/loja/pedidos/pedido/1/' . str_repeat('a', 40)));
        $this->assertSame(null, $br->session['cart.' . $this->tid] ?? null, 'carrinho esvaziado após o pedido');
    }
}
