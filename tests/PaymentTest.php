<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Request;
use App\Payments\MercadoPagoGateway;
use App\Payments\SimulatedGateway;
use App\Payments\WebhookRejected;
use App\Services\OrderService;
use App\Services\PaymentService;

/** Pagamentos simulados: idempotência, webhook assinado e "voltar para a página de sucesso" não paga nada. */
final class PaymentTest extends TestCase
{
    private array $t;
    private int $tid;
    private int $pid;
    private array $order;
    private PaymentService $payments;

    public function setUp(): void
    {
        parent::setUp();
        $this->t = $this->tenant('pagto');
        $this->tid = (int) $this->t['tenant']['id'];
        $this->pid = $this->product($this->tid, 'PAG-1', 20000, 5);
        $this->order = (new OrderService($this->db, $this->tid, null, 'customer'))->create([
            'channel' => 'store', 'customer' => ['name' => 'Cliente', 'email' => 'c@c.test'], 'shipping_method_id' => $this->shippingPickup($this->tid),
            'items' => [['product_id' => $this->pid, 'quantity' => 1]],
        ]);
        $this->payments = new PaymentService($this->db, $this->t['tenant']);
    }

    private function status(): string
    {
        return (string) $this->db->value('SELECT status FROM orders WHERE id = ?', [$this->order['id']]);
    }

    private function signedWebhook(string $status): Request
    {
        $externalId = (string) $this->db->value('SELECT external_id FROM payments WHERE order_id = ?', [$this->order['id']]);
        $signed = (new SimulatedGateway($this->db, $this->t['tenant']['uuid']))->decide($externalId, $status);
        return new Request('POST', '/webhooks/simulado/' . $this->t['tenant']['uuid'], [], [], [], $signed['headers'], body: $signed['body']);
    }

    public function testStartingPaymentTwiceIsIdempotent(): void
    {
        $url1 = $this->payments->startOnline($this->order['id'], 'http://localhost/loja/pagto/pedido/1/x');
        $url2 = $this->payments->startOnline($this->order['id'], 'http://localhost/loja/pagto/pedido/1/x');
        $this->assertSame($url1, $url2);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$this->order['id']]));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM simulated_gateway_payments'));
        $this->assertSame('awaiting_payment', $this->status(), 'criar a cobrança não paga o pedido');
    }

    public function testUnsignedOrForgedWebhookIsRejected(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $ext = (string) $this->db->value('SELECT external_id FROM payments');
        $this->db->execute("UPDATE simulated_gateway_payments SET status = 'approved'");
        $body = json_encode(['type' => 'payment', 'data' => ['id' => $ext]]);
        $forged = new Request('POST', '/webhooks/simulado/x', [], [], [], ['x-sim-timestamp' => (string) time(), 'x-sim-signature' => hash_hmac('sha256', time() . '.' . $body, 'chute')], body: $body);
        $this->assertSame(401, $this->payments->handleWebhook('simulated', $forged)[0]);
        $none = new Request('POST', '/webhooks/simulado/x', [], [], [], [], body: $body);
        $this->assertSame(401, $this->payments->handleWebhook('simulated', $none)[0]);
        $this->assertSame('awaiting_payment', $this->status());
    }

    public function testWebhookEndpointOverHttp(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $req = $this->signedWebhook('approved');
        $r = $this->browser()->post('/webhooks/simulado/' . $this->t['tenant']['uuid'], [], false, $req->headers, $req->body);
        $this->assertStatus(200, $r);
        $this->assertSame('paid', $this->status());
        $bad = $this->browser()->post('/webhooks/simulado/' . $this->t['tenant']['uuid'], [], false, [], $req->body);
        $this->assertStatus(401, $bad);
    }

    public function testApprovedSignedWebhookPaysOrderOnceAndConsumesReservation(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $req = $this->signedWebhook('approved');
        $this->assertSame([200, 'ok'], $this->payments->handleWebhook('simulated', $req));
        $this->assertSame('paid', $this->status());
        $this->assertSame(['stock_qty' => 4, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
        // Reenvio da mesma notificação
        $this->assertSame([200, 'duplicado'], $this->payments->handleWebhook('simulated', $req));
        // Nova notificação (outra assinatura) do mesmo pagamento aprovado: idempotente
        Clock::$fixed = time() + 5;
        $this->assertSame(200, $this->payments->handleWebhook('simulated', $this->signedWebhook('approved'))[0]);
        $this->assertSame(['stock_qty' => 4, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM order_status_history WHERE order_id = ? AND to_status = 'paid'", [$this->order['id']]));
    }

    public function testRejectedPaymentKeepsOrderWaiting(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $this->payments->handleWebhook('simulated', $this->signedWebhook('rejected'));
        $this->assertSame('awaiting_payment', $this->status());
        $this->assertSame('rejected', $this->db->value('SELECT status FROM payments'));
        // Nova tentativa cria outra cobrança (outra chave de idempotência)
        $this->payments->startOnline($this->order['id'], 'http://x');
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM payments'));
    }

    public function testAmountMismatchDoesNotPay(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $this->db->execute('UPDATE simulated_gateway_payments SET amount_cents = 100');
        $this->payments->handleWebhook('simulated', $this->signedWebhook('approved'));
        $this->assertSame('awaiting_payment', $this->status());
        $this->assertSame('failed', $this->db->value('SELECT status FROM webhook_events ORDER BY id DESC LIMIT 1'));
    }

    public function testWebhookCannotPayOrderOfAnotherTenant(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        $other = $this->tenant('outra-loja');
        $req = $this->signedWebhook('approved');
        // Mesma notificação enviada para a URL da outra loja: assinatura não confere (segredo por loja).
        $this->assertSame(401, (new PaymentService($this->db, $other['tenant']))->handleWebhook('simulated', $req)[0]);
        $this->assertSame('awaiting_payment', $this->status());
    }

    public function testReturningToSuccessPageDoesNotMarkPaid(): void
    {
        $br = $this->browser();
        $token = 'tok' . bin2hex(random_bytes(10));
        $this->db->execute('UPDATE orders SET tracking_token_hash = ? WHERE id = ?', [hash('sha256', $token), $this->order['id']]);
        $this->payments->startOnline($this->order['id'], 'http://x');
        $r = $br->get('/loja/pagto/pedido/' . $this->order['number'] . '/' . $token, ['status' => 'approved', 'collection_status' => 'approved', 'payment_id' => '123']);
        $this->assertStatus(200, $r);
        $this->assertContains('Aguardando a confirmação', $r->body);
        $this->assertSame('awaiting_payment', $this->status());
    }

    public function testPaymentAfterCancellationIsFlaggedNotRevived(): void
    {
        $this->payments->startOnline($this->order['id'], 'http://x');
        (new OrderService($this->db, $this->tid))->transition($this->order['id'], 'cancelled');
        $this->payments->handleWebhook('simulated', $this->signedWebhook('approved'));
        $this->assertSame('cancelled', $this->status());
        $this->assertContains('ATENÇÃO', (string) $this->db->value('SELECT note FROM order_status_history ORDER BY id DESC LIMIT 1'));
        $this->assertSame(['stock_qty' => 5, 'reserved_qty' => 0], $this->stock($this->tid, $this->pid));
    }

    public function testMercadoPagoSignatureValidationAndStatusMapping(): void
    {
        $secret = 'segredo-de-teste';
        $calls = [];
        $http = function (string $method, string $url, array $headers, ?array $body) use (&$calls) {
            $calls[] = [$method, $url, $headers, $body];
            if (str_contains($url, '/checkout/preferences')) {
                return [201, ['id' => 'pref-1', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref-1', 'sandbox_init_point' => 'https://sandbox.mercadopago.com.br/x']];
            }
            return [200, ['id' => 987654, 'status' => 'approved', 'external_reference' => 'ref-uuid', 'transaction_amount' => 199.9, 'status_detail' => 'accredited']];
        };
        $gw = new MercadoPagoGateway('TEST-token', $secret, false, \Closure::fromCallable($http));
        $res = $gw->createCheckout(['number' => 7, 'customer_email' => 'a@b.test'], [], ['uuid' => 'ref-uuid', 'amount_cents' => 19990], 'chave-idem-1', ['notification' => 'https://app/webhooks/mercadopago/u', 'return' => 'https://loja/pedido/7/t']);
        $this->assertContains('pref_id=pref-1', $res->redirectUrl);
        $this->assertTrue(in_array('X-Idempotency-Key: chave-idem-1', $calls[0][2], true), 'chave de idempotência enviada ao provedor');
        $this->assertSame(199.9, $calls[0][3]['items'][0]['unit_price']);
        $this->assertSame('ref-uuid', $calls[0][3]['external_reference']);

        $ts = '1704908010';
        $manifest = 'id:987654;request-id:req-1;ts:' . $ts . ';';
        $sig = 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, $secret);
        $ok = new Request('POST', '/webhooks/mercadopago/u', ['data_id' => '987654', 'type' => 'payment'], [], [], ['x-signature' => $sig, 'x-request-id' => 'req-1'], body: '{"type":"payment","data":{"id":"987654"}}');
        $this->assertSame('987654', $gw->verifyWebhook($ok));
        $tampered = new Request('POST', '/w', ['data_id' => '111', 'type' => 'payment'], [], [], ['x-signature' => $sig, 'x-request-id' => 'req-1']);
        $this->assertThrows(WebhookRejected::class, fn() => $gw->verifyWebhook($tampered));
        $p = $gw->fetchPayment('987654');
        $this->assertSame('approved', $p->status);
        $this->assertSame(19990, $p->amountCents);
        $this->assertSame('ref-uuid', $p->reference);
    }

    public function testGatewayCredentialsAreEncryptedAtRest(): void
    {
        $br = $this->browser();
        $br->login($this->t['owner']['email']);
        $br->get('/painel/configuracoes');
        $r = $br->post('/painel/configuracoes/pagamentos', ['access_token' => 'TEST-1234567890-segredo', 'webhook_secret' => 'assinatura-xyz', 'enabled' => '1', 'sandbox' => '1']);
        $this->assertStatus(303, $r);
        $row = $this->db->fetch('SELECT * FROM gateway_credentials WHERE tenant_id = ?', [$this->tid]);
        $this->assertNotContains('segredo', (string) $row['access_token_enc']);
        $this->assertSame('TEST-1234567890-segredo', Crypto::decrypt($row['access_token_enc']));
        $page = $br->get('/painel/configuracoes')->body;
        $this->assertNotContains('TEST-1234567890-segredo', $page, 'credencial nunca volta para a tela');
        $this->assertNotContains('segredo', (string) $this->db->value("SELECT GROUP_CONCAT(meta) FROM audit_logs WHERE action = 'settings.gateway'"));
    }
}
