<?php
declare(strict_types=1);

namespace App\Payments;

use App\Core\Clock;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Request;
use App\Core\Url;

/**
 * Gateway SIMULADO para desenvolvimento, homologação e testes. Não movimenta dinheiro.
 * Imita o fluxo real: checkout externo → notificação assinada (HMAC) → consulta do estado.
 * Fica desligado em produção (SIMULATED_GATEWAY_ENABLED=false).
 */
final class SimulatedGateway implements PaymentGateway
{
    public function __construct(private Database $db, private string $tenantUuid)
    {
    }

    public function code(): string
    {
        return 'simulated';
    }

    public static function secretFor(string $tenantUuid): string
    {
        return Crypto::derive('simulated-webhook:' . $tenantUuid);
    }

    public function createCheckout(array $order, array $items, array $payment, string $idempotencyKey, array $urls): CheckoutResult
    {
        // Idempotência do lado do "provedor": mesma chave → mesma cobrança.
        $externalId = 'SIM-' . substr(hash('sha256', $this->tenantUuid . '|' . $idempotencyKey), 0, 20);
        $this->db->execute(
            'INSERT INTO simulated_gateway_payments (external_id, reference, amount_cents, status, created_at, updated_at)
             VALUES (?, ?, ?, \'pending\', ?, ?) ON DUPLICATE KEY UPDATE updated_at = updated_at',
            [$externalId, $payment['uuid'], (int) $payment['amount_cents'], Clock::now(), Clock::now()]
        );
        return new CheckoutResult($externalId, Url::store('/pagamento-simulado/' . $payment['uuid'], ['retorno' => $urls['return']]));
    }

    /** O "provedor" muda o estado e assina a notificação, como faria um gateway real. */
    public function decide(string $externalId, string $status): array
    {
        $this->db->execute('UPDATE simulated_gateway_payments SET status = ?, updated_at = ? WHERE external_id = ?', [$status, Clock::now(), $externalId]);
        $body = json_encode(['type' => 'payment', 'data' => ['id' => $externalId]]);
        $ts = (string) Clock::ts();
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::secretFor($this->tenantUuid));
        return ['body' => $body, 'headers' => ['x-sim-timestamp' => $ts, 'x-sim-signature' => $sig]];
    }

    public function verifyWebhook(Request $request): ?string
    {
        $ts = (string) $request->header('x-sim-timestamp');
        $sig = (string) $request->header('x-sim-signature');
        $expected = hash_hmac('sha256', $ts . '.' . $request->body, self::secretFor($this->tenantUuid));
        if ($sig === '' || !hash_equals($expected, $sig) || abs(Clock::ts() - (int) $ts) > 300) {
            throw new WebhookRejected('Assinatura inválida');
        }
        $data = json_decode($request->body, true);
        if (($data['type'] ?? '') !== 'payment') {
            return null;
        }
        return (string) ($data['data']['id'] ?? '');
    }

    public function fetchPayment(string $externalId): GatewayPayment
    {
        $row = $this->db->fetch('SELECT * FROM simulated_gateway_payments WHERE external_id = ?', [$externalId]);
        if (!$row) {
            throw new \RuntimeException('Pagamento simulado inexistente');
        }
        return new GatewayPayment($row['external_id'], $row['reference'], $row['status'], (int) $row['amount_cents'], 'simulado');
    }
}
