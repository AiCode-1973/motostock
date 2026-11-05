<?php
declare(strict_types=1);

namespace App\Payments;

final class CheckoutResult
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly string $redirectUrl,
    ) {
    }
}
