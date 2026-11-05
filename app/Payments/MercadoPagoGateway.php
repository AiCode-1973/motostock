<?php
declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Mercado Pago · Checkout Pro (Pix, cartão e boleto na tela do Mercado Pago).
 *
 * SITUAÇÃO: implementado conforme a documentação pública, mas NÃO testado contra a API real —
 * depende de credenciais de cada loja (Access Token e assinatura secreta do webhook, em
 * Configurações › Pagamentos). Validar em sandbox antes de ativar (docs/06).
 *
 *  - Criação: POST /checkout/preferences com X-Idempotency-Key, external_reference = payments.uuid
 *  - Notificação: valida x-signature (HMAC-SHA256 de "id:<data.id>;request-id:<x-request-id>;ts:<ts>;")
 *  - Confirmação: GET /v1/payments/<id> (o webhook sozinho nunca marca o pedido como pago)
 */
final class MercadoPagoGateway implements PaymentGateway
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(
        private string $accessToken,
        private string $webhookSecret,
        private bool $sandbox = true,
        private ?\Closure $http = null, // injetável nos testes: fn(method, url, headers, body): [status, array]
    ) {
    }

    public function code(): string
    {
        return 'mercadopago';
    }

    public function createCheckout(array $order, array $items, array $payment, string $idempotencyKey, array $urls): CheckoutResult
    {
        $body = [
            // Um único item com o total evita divergência de arredondamento entre frete/desconto e o provedor.
            'items' => [[
                'id' => 'pedido-' . $order['number'],
                'title' => 'Pedido #' . $order['number'],
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => round(((int) $payment['amount_cents']) / 100, 2),
            ]],
            'external_reference' => $payment['uuid'],
            'notification_url' => $urls['notification'],
            'back_urls' => ['success' => $urls['return'], 'pending' => $urls['return'], 'failure' => $urls['return']],
            'auto_return' => 'approved',
            'payer' => array_filter(['email' => $order['customer_email'] ?? null]),
            'metadata' => ['order_number' => (int) $order['number']],
        ];
        [$status, $json] = $this->call('POST', '/checkout/preferences', $body, ['X-Idempotency-Key: ' . $idempotencyKey]);
        if ($status >= 300 || empty($json['init_point'])) {
            throw new \RuntimeException('Mercado Pago recusou a criação do checkout (HTTP ' . $status . ').');
        }
        $url = $this->sandbox ? ($json['sandbox_init_point'] ?? $json['init_point']) : $json['init_point'];
        return new CheckoutResult(null, (string) $url);
    }

    public function verifyWebhook(Request $request): ?string
    {
        $body = json_decode($request->body, true) ?: [];
        $type = $request->query['type'] ?? $body['type'] ?? $request->query['topic'] ?? '';
        // PHP troca "data.id" por "data_id" na query string.
        $dataId = (string) ($request->query['data_id'] ?? $request->query['data.id'] ?? $body['data']['id'] ?? '');
        $signature = (string) $request->header('x-signature');
        $requestId = (string) $request->header('x-request-id');
        $parts = [];
        foreach (explode(',', $signature) as $kv) {
            [$k, $v] = array_pad(array_map('trim', explode('=', $kv, 2)), 2, '');
            $parts[$k] = $v;
        }
        if ($this->webhookSecret === '' || empty($parts['ts']) || empty($parts['v1'])) {
            throw new WebhookRejected('Assinatura ausente');
        }
        $id = ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
        $manifest = 'id:' . $id . ';request-id:' . $requestId . ';ts:' . $parts['ts'] . ';';
        if (!hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), $parts['v1'])) {
            throw new WebhookRejected('Assinatura inválida');
        }
        if ($type !== 'payment' || $dataId === '') {
            return null;
        }
        return $dataId;
    }

    public function fetchPayment(string $externalId): GatewayPayment
    {
        if (!preg_match('/^\d{1,20}$/', $externalId)) {
            throw new \RuntimeException('Id de pagamento inválido');
        }
        [$status, $json] = $this->call('GET', '/v1/payments/' . $externalId);
        if ($status !== 200) {
            throw new \RuntimeException('Falha ao consultar pagamento no Mercado Pago (HTTP ' . $status . ').');
        }
        $mapped = match ($json['status'] ?? '') {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
        return new GatewayPayment(
            (string) $json['id'],
            (string) ($json['external_reference'] ?? ''),
            $mapped,
            (int) round(((float) ($json['transaction_amount'] ?? 0)) * 100),
            (string) ($json['status_detail'] ?? '')
        );
    }

    private function call(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $headers = array_merge(['Authorization: Bearer ' . $this->accessToken, 'Content-Type: application/json'], $headers);
        if ($this->http) {
            return ($this->http)($method, self::API . $path, $headers, $body);
        }
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('Sem resposta do Mercado Pago.');
        }
        return [$status, json_decode((string) $raw, true) ?: []];
    }
}
