<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Env;
use App\Services\OrderService;

/** Autorização no backend: cada função só faz o que lhe cabe, mesmo chamando a rota direto. */
final class PermissionTest extends TestCase
{
    private array $t;
    private int $tid;

    public function setUp(): void
    {
        parent::setUp();
        $this->t = $this->tenant('perm');
        $this->tid = (int) $this->t['tenant']['id'];
        $this->member($this->tid, 'seller', 'vendedor@perm.test');
        $this->member($this->tid, 'manager', 'gerente@perm.test');
    }

    private function as(string $email): Browser
    {
        $br = $this->browser();
        $br->login($email);
        return $br;
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $br = $this->browser();
        $this->assertRedirectTo('/entrar', $br->get('/painel'));
        $this->assertRedirectTo('/entrar', $br->get('/painel/produtos'));
        $this->assertRedirectTo('/entrar', $br->get('/plataforma'));
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $br = $this->as('vendedor@perm.test');
        $r = $br->post('/painel/clientes', ['name' => 'Sem token'], false);
        $this->assertStatus(419, $r);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM customers'));
    }

    public function testSellerCannotManageTeamSettingsOrCatalog(): void
    {
        $br = $this->as('vendedor@perm.test');
        $this->assertStatus(200, $br->get('/painel/pedidos'));
        $this->assertStatus(200, $br->get('/painel/produtos'));
        foreach (['/painel/usuarios', '/painel/configuracoes', '/painel/relatorios', '/painel/compras', '/painel/fornecedores', '/painel/privacidade', '/painel/exportar-dados', '/painel/auditoria', '/painel/produtos/novo'] as $path) {
            $this->assertStatus(403, $br->get($path), $path);
        }
        $this->assertStatus(403, $br->post('/painel/usuarios', ['name' => 'X', 'email' => 'x@x.test', 'role' => 'manager']));
        $this->assertStatus(403, $br->post('/painel/produtos', ['sku' => 'S', 'name' => 'N', 'price_cents' => '1,00']));
        $this->assertStatus(403, $br->post('/painel/estoque/movimentar', ['product_id' => 1, 'type' => 'entry', 'quantity' => 1, 'reason' => 'x']));
        $this->assertStatus(403, $br->post('/painel/configuracoes/pagamentos', ['enabled' => '1']));
    }

    public function testSellerDoesNotSeeCostAndMargin(): void
    {
        $pid = $this->product($this->tid, 'CUSTO-1', 10000, 5, 4321);
        $html = $this->as('vendedor@perm.test')->get('/painel/produtos/' . $pid)->body;
        $this->assertNotContains('43,21', $html, 'vendedor não vê o custo');
        $this->assertNotContains('Margem', $html);
        $this->assertContains('43,21', $this->as('gerente@perm.test')->get('/painel/produtos/' . $pid)->body);
    }

    public function testSellerCannotRefundButManagerCan(): void
    {
        $pid = $this->product($this->tid, 'REF-1', 10000, 5);
        $order = (new OrderService($this->db, $this->tid))->create(['channel' => 'manual', 'items' => [['product_id' => $pid, 'quantity' => 1]]]);
        (new \App\Services\PaymentService($this->db, $this->t['tenant']))->recordManual($order['id'], 'cash', (int) $this->t['owner']['id'], null);
        $this->assertStatus(403, $this->as('vendedor@perm.test')->post('/painel/pedidos/' . $order['id'] . '/status', ['to' => 'refunded']));
        $this->assertSame('paid', $this->db->value('SELECT status FROM orders WHERE id = ?', [$order['id']]));
        $this->assertStatus(303, $this->as('gerente@perm.test')->post('/painel/pedidos/' . $order['id'] . '/status', ['to' => 'refunded']));
        $this->assertSame('refunded', $this->db->value('SELECT status FROM orders WHERE id = ?', [$order['id']]));
    }

    public function testSellerDiscountIsLimited(): void
    {
        $pid = $this->product($this->tid, 'DESC-1', 10000, 5);
        $br = $this->as('vendedor@perm.test');
        $br->get('/painel/pedidos/novo');
        $r = $br->post('/painel/pedidos', ['items' => [['product_id' => $pid, 'quantity' => 1]], 'discount' => '20,00'], true, ['referer' => 'http://localhost/painel/pedidos/novo']);
        $this->assertRedirectTo('/painel/pedidos/novo', $r);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM orders'));
        $this->assertStatus(303, $br->post('/painel/pedidos', ['items' => [['product_id' => $pid, 'quantity' => 1]], 'discount' => '10,00']));
        $this->assertSame(9000, (int) $this->db->value('SELECT total_cents FROM orders'));
    }

    public function testManagerCannotTouchOwnerOnlyAreas(): void
    {
        $br = $this->as('gerente@perm.test');
        $this->assertStatus(200, $br->get('/painel/relatorios'));
        $this->assertStatus(403, $br->get('/painel/usuarios'));
        $this->assertStatus(403, $br->get('/painel/configuracoes'));
        $this->assertStatus(403, $br->get('/painel/exportar-dados'));
    }

    public function testDisabledMemberLosesAccessImmediately(): void
    {
        $br = $this->as('vendedor@perm.test');
        $this->assertStatus(200, $br->get('/painel/pedidos'));
        $this->db->execute("UPDATE tenant_users SET status = 'disabled' WHERE user_id = (SELECT id FROM users WHERE email = 'vendedor@perm.test')");
        $this->assertStatus(403, $br->get('/painel/pedidos'));
    }

    public function testOwnerCannotDemoteLastOwnerOrSelf(): void
    {
        $br = $this->as($this->t['owner']['email']);
        $memberId = (int) $this->db->value('SELECT id FROM tenant_users WHERE user_id = ?', [$this->t['owner']['id']]);
        $br->get('/painel/usuarios');
        $br->post('/painel/usuarios/' . $memberId, ['role' => 'seller', 'status' => 'active']);
        $this->assertSame('owner', $this->db->value('SELECT role FROM tenant_users WHERE id = ?', [$memberId]));
    }

    public function testStoreOwnerCannotOpenPlatformAndSupportIsReadOnly(): void
    {
        $this->assertStatus(403, $this->as($this->t['owner']['email'])->get('/plataforma'));
        $this->db->execute("UPDATE users SET platform_role = 'support' WHERE email = 'gerente@perm.test'");
        $br = $this->as('gerente@perm.test');
        $this->assertStatus(200, $br->get('/plataforma/lojas'));
        $this->assertStatus(403, $br->post('/plataforma/lojas/' . $this->tid . '/status', ['to' => 'suspended', 'reason' => 'x']));
        $this->assertSame('trial', $this->db->value('SELECT status FROM tenants WHERE id = ?', [$this->tid]));
    }

    public function testPlatformRequiresMfaWhenConfigured(): void
    {
        $this->db->execute("UPDATE users SET platform_role = 'admin' WHERE email = 'gerente@perm.test'");
        Env::set('PLATFORM_REQUIRE_MFA', 'true');
        try {
            $this->assertRedirectTo('/conta/seguranca', $this->as('gerente@perm.test')->get('/plataforma'));
        } finally {
            Env::set('PLATFORM_REQUIRE_MFA', 'false');
        }
    }

    public function testPlatformAdminActionsAreAudited(): void
    {
        $this->db->execute("UPDATE users SET platform_role = 'admin' WHERE email = 'gerente@perm.test'");
        $br = $this->as('gerente@perm.test');
        $br->get('/plataforma/lojas/' . $this->tid);
        $br->post('/plataforma/lojas/' . $this->tid . '/status', ['to' => 'suspended', 'reason' => 'teste de auditoria']);
        $this->assertSame('suspended', $this->db->value('SELECT status FROM tenants WHERE id = ?', [$this->tid]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE action = 'tenant.viewed' AND tenant_id = ?", [$this->tid]));
        $meta = (string) $this->db->value("SELECT meta FROM audit_logs WHERE action = 'tenant.status' AND tenant_id = ?", [$this->tid]);
        $this->assertContains('teste de auditoria', $meta);
    }
}
