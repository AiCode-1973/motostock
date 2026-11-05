<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Url;
use App\Payments\MercadoPagoGateway;
use App\Payments\PaymentGateway;
use App\Payments\SimulatedGateway;
use App\Payments\WebhookRejected;

/**
 * Orquestra pagamentos: criação idempotente, processamento de webhooks e registro manual (balcão).
 * Regra central: pedido só vira "pago" após notificação ASSINADA + CONSULTA ao provedor + valor conferido.
 */
final class PaymentService
{
    public function __construct(private Database $db, private array $tenant)
    {
    }

    public static ?PaymentGateway $override = null; // testes

    public function gateway(?string $code = null): PaymentGateway
    {
        if (self::$override) {
            return self::$override;
        }
        $code ??= $this->activeGatewayCode();
        if ($code === 'mercadopago') {
            $cred = $this->db->fetch('SELECT * FROM gateway_credentials WHERE tenant_id = ? AND gateway = ?', [$this->tenant['id'], 'mercadopago']);
            if (!$cred || !$cred['enabled']) {
                throw new BusinessException('Pagamento online indisponível: a loja ainda não configurou o Mercado Pago.');
            }
            return new MercadoPagoGateway(
                (string) Crypto::decrypt($cred['access_token_enc']),
                (string) Crypto::decrypt($cred['webhook_secret_enc']),
                (bool) $cred['sandbox']
            );
        }
        if ($code === 'simulated' && Env::bool('SIMULATED_GATEWAY_ENABLED', false)) {
            return new SimulatedGateway($this->db, $this->tenant['uuid']);
        }
        throw new BusinessException('Pagamento online indisponível no momento.');
    }

    /** Mercado Pago se a loja configurou; senão o simulado (apenas se habilitado no ambiente). */
    public function activeGatewayCode(): string
    {
        $mp = $this->db->value('SELECT enabled FROM gateway_credentials WHERE tenant_id = ? AND gateway = ?', [$this->tenant['id'], 'mercadopago']);
        if ($mp) {
            return 'mercadopago';
        }
        return Env::bool('SIMULATED_GATEWAY_ENABLED', false) ? 'simulated' : 'none';
    }

    public function onlineAvailable(): bool
    {
        return $this->activeGatewayCode() !== 'none';
    }

    /**
     * Inicia (ou reaproveita) o pagamento online de um pedido.
     * Duplo clique / nova tentativa com o mesmo valor reaproveitam o pagamento pendente.
     */
    public function startOnline(int $orderId, string $trackingUrl): string
    {
        $tid = (int) $this->tenant['id'];
        $order = $this->db->fetch('SELECT * FROM orders WHERE tenant_id = ? AND id = ?', [$tid, $orderId]) ?? throw HttpException::notFound();
        if ($order['status'] !== 'awaiting_payment') {
            throw new BusinessException('Este pedido não está aguardando pagamento.');
        }
        $gateway = $this->gateway();
        $existing = $this->db->fetch(
            "SELECT * FROM payments WHERE tenant_id = ? AND order_id = ? AND gateway = ? AND status = 'pending' AND amount_cents = ? AND checkout_url IS NOT NULL ORDER BY id DESC LIMIT 1",
            [$tid, $orderId, $gateway->code(), $order['total_cents']]
        );
        if ($existing) {
            return $existing['checkout_url'];
        }
        $attempt = 1 + (int) $this->db->value('SELECT COUNT(*) FROM payments WHERE tenant_id = ? AND order_id = ?', [$tid, $orderId]);
        $key = sprintf('order-%d-%d-%s-%d', $orderId, $order['total_cents'], $gateway->code(), $attempt);
        $uuid = uuid4();
        try {
            $paymentId = $this->db->insert('payments', [
                'tenant_id' => $tid, 'order_id' => $orderId, 'uuid' => $uuid, 'gateway' => $gateway->code(),
                'method' => 'online', 'status' => 'pending', 'amount_cents' => (int) $order['total_cents'],
                'idempotency_key' => $key, 'created_at' => Clock::now(), 'updated_at' => Clock::now(),
            ]);
        } catch (\PDOException $e) {
            // Corrida entre dois cliques: a chave única já existe; reaproveita.
            $row = $this->db->fetch('SELECT * FROM payments WHERE tenant_id = ? AND idempotency_key = ?', [$tid, $key]);
            if ($row && $row['checkout_url']) {
                return $row['checkout_url'];
            }
            throw new BusinessException('Pagamento em processamento. Aguarde alguns segundos e atualize a página.');
        }
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        $items = $this->db->fetchAll('SELECT * FROM order_items WHERE tenant_id = ? AND order_id = ?', [$tid, $orderId]);
        $result = $gateway->createCheckout($order, $items, $payment, $key, [
            'notification' => Url::absolute('webhooks/' . ($gateway->code() === 'mercadopago' ? 'mercadopago' : 'simulado') . '/' . $this->tenant['uuid']),
            'return' => $trackingUrl,
        ]);
        $this->db->execute(
            'UPDATE payments SET external_id = ?, checkout_url = ?, updated_at = ? WHERE id = ?',
            [$result->externalId, $result->redirectUrl, Clock::now(), $paymentId]
        );
        return $result->redirectUrl;
    }

    /**
     * Processa uma notificação. Devolve [status HTTP, mensagem].
     * Ordem: valida assinatura → deduplica evento → consulta provedor → confere referência e valor → atualiza.
     */
    public function handleWebhook(string $gatewayCode, Request $request): array
    {
        $tid = (int) $this->tenant['id'];
        $gateway = $this->gateway($gatewayCode);
        try {
            $externalId = $gateway->verifyWebhook($request);
        } catch (WebhookRejected $e) {
            return [401, 'assinatura inválida'];
        }
        if ($externalId === null || $externalId === '') {
            return [200, 'ignorado'];
        }
        $eventKey = $tid . ':' . $externalId . ':' . hash('sha256', $request->body . ($request->header('x-request-id') ?? '') . ($request->header('x-sim-timestamp') ?? ''));
        $inserted = $this->db->execute(
            'INSERT IGNORE INTO webhook_events (gateway, event_key, tenant_id, payload_sha256, status, created_at) VALUES (?, ?, ?, ?, \'received\', ?)',
            [$gateway->code(), substr($eventKey, 0, 190), $tid, hash('sha256', $request->body), Clock::now()]
        );
        if ($inserted === 0) {
            return [200, 'duplicado'];
        }
        $remote = $gateway->fetchPayment($externalId);
        $payment = $this->db->fetch('SELECT * FROM payments WHERE tenant_id = ? AND uuid = ?', [$tid, $remote->reference]);
        if (!$payment) {
            $this->markEvent($eventKey, 'ignored', 'referência de outra loja ou inexistente');
            return [200, 'referência desconhecida'];
        }
        if ($remote->status === 'approved' && $remote->amountCents !== (int) $payment['amount_cents']) {
            $this->markEvent($eventKey, 'failed', 'valor divergente');
            $this->db->execute("UPDATE payments SET status_detail = 'valor divergente: verificar', updated_at = ? WHERE id = ?", [Clock::now(), $payment['id']]);
            return [200, 'valor divergente'];
        }
        $this->db->transaction(function () use ($payment, $remote, $tid) {
            $this->db->execute(
                'UPDATE payments SET status = ?, external_id = ?, status_detail = ?, updated_at = ? WHERE id = ?',
                [$remote->status, $remote->externalId, mb_substr($remote->detail, 0, 120), Clock::now(), $payment['id']]
            );
            if ($remote->status === 'approved' && $payment['status'] !== 'approved') {
                (new OrderService($this->db, $tid, null, 'gateway'))
                    ->markPaidByGateway((int) $payment['order_id'], 'Pagamento confirmado pelo provedor (' . $remote->externalId . ')');
            }
        });
        $this->markEvent($eventKey, 'processed', $remote->status);
        return [200, 'ok'];
    }

    /** Pagamento recebido no balcão (dinheiro, maquininha, Pix conferido). Exige permissão no controller. */
    public function recordManual(int $orderId, string $method, int $userId, ?string $note): void
    {
        $tid = (int) $this->tenant['id'];
        if (!in_array($method, ['cash', 'pix_manual', 'card_pos', 'transfer'], true)) {
            throw new BusinessException('Forma de pagamento inválida.');
        }
        $this->db->transaction(function () use ($tid, $orderId, $method, $userId, $note) {
            $order = $this->db->fetch('SELECT * FROM orders WHERE tenant_id = ? AND id = ? FOR UPDATE', [$tid, $orderId]) ?? throw HttpException::notFound();
            if ($order['status'] !== 'awaiting_payment') {
                throw new BusinessException('Este pedido não está aguardando pagamento.');
            }
            $this->db->insert('payments', [
                'tenant_id' => $tid, 'order_id' => $orderId, 'uuid' => uuid4(), 'gateway' => 'manual', 'method' => $method,
                'status' => 'approved', 'amount_cents' => (int) $order['total_cents'],
                'idempotency_key' => 'manual-' . $orderId, 'recorded_by' => $userId,
                'status_detail' => $note ? mb_substr($note, 0, 120) : null,
                'created_at' => Clock::now(), 'updated_at' => Clock::now(),
            ]);
            $this->db->execute('UPDATE orders SET payment_method = ? WHERE tenant_id = ? AND id = ?', [$method, $tid, $orderId]);
            (new OrderService($this->db, $tid, $userId))->transition($orderId, 'paid', ['note' => 'Pagamento registrado no balcão: ' . \App\Support\Labels::get('pay_method', $method)]);
        });
    }

    private function markEvent(string $eventKey, string $status, string $message): void
    {
        $this->db->execute(
            'UPDATE webhook_events SET status = ?, message = ?, processed_at = ? WHERE event_key = ?',
            [$status, $message, Clock::now(), substr($eventKey, 0, 190)]
        );
    }
}
