<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Clock;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\TenantExportService;
use App\Support\Period;

/** Um usuário da loja A tenta acessar dados da loja B por todos os caminhos: tudo deve ser negado. */
final class TenantIsolationTest extends TestCase
{
    private array $a;
    private array $b;
    private int $productB;
    private int $orderB;
    private int $customerB;

    public function setUp(): void
    {
        parent::setUp();
        $this->a = $this->tenant('loja-a');
        $this->b = $this->tenant('loja-b');
        $tb = (int) $this->b['tenant']['id'];
        $this->productB = $this->product($tb, 'SKU-B', 15000, 5);
        $this->customerB = (new CustomerService($this->db, $tb))->create(['name' => 'Cliente da B', 'email' => 'cliente@b.test']);
        $this->orderB = (new OrderService($this->db, $tb))->create(['channel' => 'manual', 'customer_id' => $this->customerB, 'items' => [['product_id' => $this->productB, 'quantity' => 1]]])['id'];
    }

    private function asOwnerA(): Browser
    {
        $br = $this->browser();
        $this->assertRedirectTo('/painel', $br->login($this->a['owner']['email']));
        return $br;
    }

    public function testReadProductOfOtherTenantReturns404(): void
    {
        $br = $this->asOwnerA();
        $this->assertStatus(404, $br->get('/painel/produtos/' . $this->productB));
        $this->assertStatus(200, $br->get('/painel/produtos'));
        $this->assertNotContains('SKU-B', $br->get('/painel/produtos')->body, 'lista da loja A não mostra produto da B');
    }

    public function testUpdateProductOfOtherTenantIsDenied(): void
    {
        $br = $this->asOwnerA();
        $r = $br->post('/painel/produtos/' . $this->productB, ['sku' => 'SKU-B', 'name' => 'Hackeado', 'price_cents' => '1,00', 'active' => '1']);
        $this->assertStatus(404, $r);
        $this->assertSame('Produto SKU-B', $this->db->value('SELECT name FROM products WHERE id = ?', [$this->productB]));
    }

    public function testStockMovementOnOtherTenantProductIsDenied(): void
    {
        $br = $this->asOwnerA();
        $before = $this->stock((int) $this->b['tenant']['id'], $this->productB);
        $r = $br->post('/painel/estoque/movimentar', ['product_id' => $this->productB, 'type' => 'exit', 'quantity' => 1, 'reason' => 'ataque']);
        $this->assertStatus(404, $r);
        $this->assertSame($before, $this->stock((int) $this->b['tenant']['id'], $this->productB));
    }

    public function testOrdersAndCustomersOfOtherTenantAreHidden(): void
    {
        $br = $this->asOwnerA();
        $this->assertStatus(404, $br->get('/painel/pedidos/' . $this->orderB));
        $this->assertStatus(404, $br->post('/painel/pedidos/' . $this->orderB . '/status', ['to' => 'cancelled']));
        $this->assertStatus(404, $br->post('/painel/pedidos/' . $this->orderB . '/pagamento', ['method' => 'cash']));
        $this->assertStatus(404, $br->get('/painel/clientes/' . $this->customerB));
        $this->assertStatus(404, $br->post('/painel/clientes/' . $this->customerB, ['name' => 'Alterado']));
        $this->assertNotContains('Cliente da B', $br->get('/painel/clientes')->body);
        $this->assertSame('awaiting_payment', $this->db->value('SELECT status FROM orders WHERE id = ?', [$this->orderB]));
    }

    public function testTenantIdSentByBrowserIsIgnored(): void
    {
        $br = $this->asOwnerA();
        $r = $br->post('/painel/produtos', ['tenant_id' => $this->b['tenant']['id'], 'sku' => 'NOVO-1', 'name' => 'Produto novo', 'price_cents' => '10,00', 'active' => '1']);
        $this->assertStatus(303, $r);
        $this->assertSame((int) $this->a['tenant']['id'], (int) $this->db->value('SELECT tenant_id FROM products WHERE sku = ?', ['NOVO-1']));
    }

    public function testSessionPointingToForeignTenantFallsBackToOwnMembership(): void
    {
        $br = $this->asOwnerA();
        $br->session['tenant_id'] = (int) $this->b['tenant']['id']; // sessão adulterada
        $r = $br->get('/painel/produtos');
        $this->assertStatus(200, $r);
        $this->assertContains('Loja loja-a', $r->body);
        $this->assertNotContains('SKU-B', $r->body);
        $this->assertSame((int) $this->a['tenant']['id'], $br->session['tenant_id']);
    }

    public function testCannotSwitchToTenantWithoutMembership(): void
    {
        $br = $this->asOwnerA();
        $this->assertStatus(403, $br->post('/conta/trocar-loja', ['tenant_id' => $this->b['tenant']['id']]));
    }

    public function testMediaOfOtherTenantIsNotServed(): void
    {
        $tb = (int) $this->b['tenant']['id'];
        $imgId = $this->db->insert('product_images', ['tenant_id' => $tb, 'product_id' => $this->productB, 'file_name' => 'x.png', 'mime' => 'image/png', 'size_bytes' => 1, 'created_at' => Clock::now()]);
        $br = $this->asOwnerA();
        $this->assertStatus(404, $br->get('/painel/midia/' . $imgId));
        $this->assertStatus(404, $this->browser()->get('/loja/loja-a/midia/' . $imgId));
    }

    public function testStorefrontOfAHidesProductsOfB(): void
    {
        $slugB = $this->db->value('SELECT slug FROM products WHERE id = ?', [$this->productB]);
        $br = $this->browser();
        $this->assertStatus(404, $br->get('/loja/loja-a/produto/' . $slugB));
        $this->assertStatus(200, $br->get('/loja/loja-b/produto/' . $slugB));
        $this->assertNotContains('SKU-B', $br->get('/loja/loja-a/')->body);
        // Adicionar ao carrinho da loja A o id de um produto da B
        $br->get('/loja/loja-a/');
        $r = $br->post('/loja/loja-a/carrinho/adicionar', ['product_id' => $this->productB, 'quantity' => 1], true, ['referer' => 'http://localhost/loja/loja-a/']);
        $this->assertStatus(303, $r);
        $this->assertContains('indisponível', strtolower($br->session['_flash'][0]['message'] ?? ''));
    }

    public function testSubdomainResolvesOnlyItsOwnTenant(): void
    {
        $slugB = $this->db->value('SELECT slug FROM products WHERE id = ?', [$this->productB]);
        $this->assertStatus(404, $this->browser('loja-a.localhost')->get('/produto/' . $slugB));
        $this->assertStatus(200, $this->browser('loja-b.localhost')->get('/produto/' . $slugB));
        $this->assertStatus(404, $this->browser('inexistente.localhost')->get('/'));
    }

    public function testExportAndReportsOnlyContainOwnData(): void
    {
        $ta = (int) $this->a['tenant']['id'];
        $this->product($ta, 'SKU-A', 5000, 3);
        $json = (new TenantExportService($this->db))->export($ta);
        $this->assertContains('SKU-A', $json);
        $this->assertNotContains('SKU-B', $json);
        $this->assertNotContains('Cliente da B', $json);
        $this->assertNotContains('password_hash', $json, 'exportação não leva hash de senha');

        $tb = (int) $this->b['tenant']['id'];
        (new \App\Services\PaymentService($this->db, $this->b['tenant']))->recordManual($this->orderB, 'cash', (int) $this->b['owner']['id'], null);
        $p = Period::fromInput(null, null, 'America/Sao_Paulo');
        $this->assertSame(0, (new ReportService($this->db, $ta))->dashboard($p)['revenue'], 'faturamento da B não entra na A');
        $this->assertSame(15000, (new ReportService($this->db, $tb))->dashboard($p)['revenue']);
        $this->assertSame([], (new ReportService($this->db, $ta))->run('vendas', $p)['rows']);

        $br = $this->asOwnerA();
        $csv = $br->get('/painel/relatorios/estoque/csv')->body;
        $this->assertContains('SKU-A', $csv);
        $this->assertNotContains('SKU-B', $csv);
        $exp = $br->get('/painel/exportar-dados')->body;
        $this->assertNotContains('SKU-B', $exp);
    }

    public function testDatabaseRejectsCrossTenantReferences(): void
    {
        $ta = (int) $this->a['tenant']['id'];
        $orderA = (new OrderService($this->db, $ta))->create(['channel' => 'manual', 'items' => [['product_id' => $this->product($ta, 'SKU-A2'), 'quantity' => 1]]])['id'];
        $this->assertThrows(\PDOException::class, fn() => $this->db->insert('order_items', [
            'tenant_id' => $ta, 'order_id' => $orderA, 'product_id' => $this->productB, 'sku' => 'x', 'name' => 'x',
            'unit_price_cents' => 1, 'quantity' => 1, 'total_cents' => 1,
        ]));
        $this->assertThrows(\PDOException::class, fn() => $this->db->execute('UPDATE products SET category_id = ? WHERE id = ?', [
            $this->db->insert('categories', ['tenant_id' => (int) $this->b['tenant']['id'], 'name' => 'Cat B', 'slug' => 'cat-b', 'created_at' => Clock::now()]),
            $this->product($ta, 'SKU-A3'),
        ]));
    }

    public function testServiceLayerCannotCreateOrderWithForeignProduct(): void
    {
        $ta = (int) $this->a['tenant']['id'];
        $this->assertThrows(\App\Core\BusinessException::class, fn() => (new OrderService($this->db, $ta))->create([
            'channel' => 'manual', 'items' => [['product_id' => $this->productB, 'quantity' => 1]],
        ]), 'não está mais disponível');
        $this->assertThrows(\App\Core\HttpException::class, fn() => (new OrderService($this->db, $ta))->create([
            'channel' => 'manual', 'customer_id' => $this->customerB, 'items' => [['product_id' => $this->product($ta, 'SKU-A4'), 'quantity' => 1]],
        ]));
    }

    public function testCustomerAccountIsScopedToItsStore(): void
    {
        $br = $this->browser();
        $br->get('/loja/loja-b/');
        $r = $br->post('/loja/loja-b/conta/cadastro', ['name' => 'Cliente', 'email' => 'mesmo@email.test', 'password' => 'senha-cliente-1', 'accept_privacy' => '1']);
        $this->assertStatus(303, $r);
        $this->assertStatus(200, $br->get('/loja/loja-b/conta/pedidos'));
        // A mesma sessão não está logada na loja A
        $this->assertRedirectTo('/loja/loja-a/conta/entrar', $br->get('/loja/loja-a/conta/pedidos'));
        // E a senha da loja B não abre a loja A
        $br->post('/loja/loja-a/conta/entrar', ['email' => 'mesmo@email.test', 'password' => 'senha-cliente-1']);
        $this->assertRedirectTo('/loja/loja-a/conta/entrar', $br->get('/loja/loja-a/conta/pedidos'));
    }
}
