<?php
declare(strict_types=1);

namespace Tests;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Services\CustomerService;
use App\Services\OrderService;
use App\Services\TenantService;
use App\Support\Csv;
use App\Support\Period;
use App\Core\Money;
use App\Core\Validator;

/** Ciclo de vida do tenant, LGPD e utilitários. */
final class LifecycleTest extends TestCase
{
    public function testSuspensionTakesStoreOfflineWithoutDeletingData(): void
    {
        $t = $this->tenant('ciclo');
        $tid = (int) $t['tenant']['id'];
        $this->product($tid, 'CIC-1');
        $svc = new TenantService($this->db);
        $svc->changeStatus($tid, 'suspended', 'inadimplência', 1);
        $this->assertStatus(503, $this->browser()->get('/loja/ciclo/'));
        $br = $this->browser();
        $br->login($t['owner']['email']);
        $this->assertRedirectTo('/painel/assinatura', $br->get('/painel/produtos'));
        $this->assertStatus(200, $br->get('/painel/assinatura'));
        $this->assertStatus(200, $br->get('/painel/exportar-dados'), 'exportação disponível mesmo suspensa');
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tid]));

        $svc->changeStatus($tid, 'active', 'pagamento regularizado', 1);
        $this->assertStatus(200, $this->browser()->get('/loja/ciclo/'));
        $this->assertStatus(200, $br->get('/painel/produtos'));
    }

    public function testCancellationSetsRetentionAndAllowsReactivationInsideWindow(): void
    {
        $tid = (int) $this->tenant('cancela')['tenant']['id'];
        $svc = new TenantService($this->db);
        $this->assertThrows(BusinessException::class, fn() => $svc->changeStatus($tid, 'cancelled', '', 1), 'motivo');
        $svc->changeStatus($tid, 'cancelled', 'pedido do cliente', 1);
        $t = $this->db->fetch('SELECT * FROM tenants WHERE id = ?', [$tid]);
        $this->assertSame('cancelled', $t['status']);
        $this->assertTrue($t['retention_until'] > Clock::now('+89 days'), 'retenção de 90 dias');
        $this->assertSame('cancelled', $this->db->value('SELECT status FROM subscriptions WHERE tenant_id = ?', [$tid]));
        $this->assertThrows(BusinessException::class, fn() => $svc->changeStatus($tid, 'suspended', 'x', 1), 'não permitida');
        Clock::$fixed = time() + 91 * 86400;
        $this->assertThrows(BusinessException::class, fn() => $svc->changeStatus($tid, 'active', 'volta', 1), 'retenção');
    }

    public function testTrialExpiryMovesToPastDueOnly(): void
    {
        $tid = (int) $this->tenant('teste-fim', $this->plan(['trial_days' => 3]))['tenant']['id'];
        $svc = new TenantService($this->db);
        $this->assertSame(0, $svc->expireTrials());
        Clock::$fixed = time() + 4 * 86400;
        $this->assertSame(1, $svc->expireTrials());
        $this->assertSame('past_due', $this->db->value('SELECT status FROM tenants WHERE id = ?', [$tid]));
        $this->assertStatus(200, $this->browser()->get('/loja/teste-fim/'), 'pagamento pendente não derruba a loja');
    }

    public function testAnonymizationRemovesPersonalDataButKeepsOrderValues(): void
    {
        $tid = (int) $this->tenant('lgpd')['tenant']['id'];
        $pid = $this->product($tid, 'LG-1', 3000, 5);
        $cs = new CustomerService($this->db, $tid);
        $cid = $cs->create(['name' => 'Maria Titular', 'email' => 'maria@t.test', 'phone' => '11999998888', 'document' => '529.982.247-25']);
        $o = (new OrderService($this->db, $tid))->create(['channel' => 'manual', 'customer_id' => $cid, 'items' => [['product_id' => $pid, 'quantity' => 1]]]);
        $cs->anonymize($cid);
        $c = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$cid]);
        $this->assertSame(null, $c['email']);
        $this->assertSame(null, $c['document']);
        $order = $this->db->fetch('SELECT * FROM orders WHERE id = ?', [$o['id']]);
        $this->assertNotContains('Maria', $order['customer_name']);
        $this->assertSame(null, $order['customer_email']);
        $this->assertSame(3000, (int) $order['total_cents']);
        $this->assertThrows(BusinessException::class, fn() => $cs->anonymize($cid));
    }

    public function testDataSubjectRequestFromStorefront(): void
    {
        $tid = (int) $this->tenant('titular')['tenant']['id'];
        $br = $this->browser();
        $br->get('/loja/titular/privacidade/solicitar');
        $this->assertStatus(303, $br->post('/loja/titular/privacidade/solicitar', ['email' => 'pessoa@x.test', 'type' => 'deletion']));
        $this->assertSame('deletion', $this->db->value('SELECT type FROM data_requests WHERE tenant_id = ?', [$tid]));
    }

    public function testCronExpiresOrdersPerTenant(): void
    {
        $t = $this->tenant('cron');
        $tid = (int) $t['tenant']['id'];
        $pid = $this->product($tid, 'CR-1', 1000, 3);
        (new OrderService($this->db, $tid))->create(['channel' => 'store', 'customer' => ['name' => 'X'], 'shipping_method_id' => $this->shippingPickup($tid), 'items' => [['product_id' => $pid, 'quantity' => 3]]]);
        $this->db->execute('UPDATE orders SET expires_at = ? WHERE tenant_id = ?', [Clock::now('-1 hour'), $tid]);
        ob_start();
        require BASE_PATH . '/bin/cron.php';
        $out = (string) ob_get_clean();
        $this->assertContains('pedidos_expirados=1', $out);
        $this->assertSame(0, $this->stock($tid, $pid)['reserved_qty']);
    }

    public function testMoneyPeriodCsvAndDocuments(): void
    {
        $this->assertSame(123456, Money::parse('1.234,56'));
        $this->assertSame(12990, Money::parse('129,9'));
        $this->assertSame(12990, Money::parse('129.90'));
        $this->assertSame(null, Money::parse('12,345'));
        $this->assertSame('R$ 1.234,56', Money::format(123456));
        $p = Period::fromInput('2026-11-01', '2026-11-30', 'America/Sao_Paulo');
        $this->assertSame('2026-11-01 03:00:00', $p->startUtc());
        $this->assertSame('2026-12-01 03:00:00', $p->endUtc());
        $this->assertContains("'=HYPERLINK(", Csv::build(['a'], [['=HYPERLINK("http://x")']]), 'CSV neutraliza fórmula');
        $this->assertContains(';-10', Csv::build(['a', 'b'], [['x', '-10']]), 'número negativo preservado');
        $this->assertTrue(Validator::validCnpj('11222333000181'));
        $this->assertFalse(Validator::validCnpj('11222333000182'));
        $this->assertTrue(Validator::validCpf('52998224725'));
        $this->assertFalse(Validator::validCpf('11111111111'));
    }
}
