<?php
declare(strict_types=1);

namespace App\Payments;

use App\Core\Request;

/**
 * Contrato de um provedor de pagamento. Dados de cartão nunca passam pelo MotoStock:
 * o cliente paga na tela do provedor (checkout hospedado / componentes seguros).
 */
interface PaymentGateway
{
    public function code(): string;

    /**
     * Cria a cobrança no provedor. $idempotencyKey deve ser repassada ao provedor para evitar cobrança duplicada.
     * @param array $order linha de orders; $payment linha de payments; $urls ['notification' => ..., 'return' => ...]
     */
    public function createCheckout(array $order, array $items, array $payment, string $idempotencyKey, array $urls): CheckoutResult;

    /**
     * Valida a assinatura da notificação e devolve o id externo do pagamento.
     * Lança WebhookRejected se a assinatura for inválida. Devolve null para eventos que não são de pagamento.
     */
    public function verifyWebhook(Request $request): ?string;

    /** Consulta o estado real do pagamento no provedor (fonte de verdade). */
    public function fetchPayment(string $externalId): GatewayPayment;
}
