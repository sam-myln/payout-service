<?php declare(strict_types=1);

namespace Processing;

final readonly class PaymentResult
{
    public function __construct(public string $providerPayoutId, public string $status)
    {
    }
}
