<?php
declare(strict_types=1);

namespace App\Payments;

/** Estado de um pagamento como o provedor informa, já traduzido para nossos estados. */
final class GatewayPayment
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $reference,   // payments.uuid
        public readonly string $status,      // pending | approved | rejected | cancelled | refunded
        public readonly int $amountCents,
        public readonly string $detail = '',
    ) {
    }
}
