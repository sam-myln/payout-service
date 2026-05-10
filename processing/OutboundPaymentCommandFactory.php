<?php declare(strict_types=1);

namespace Processing;

final class OutboundPaymentCommandFactory
{
    public function create(
        int $userId,
        string $amount,
        string $currency,
        string $wallet,
        string $externalReference
    ): OutboundPaymentCommand {
        return new OutboundPaymentCommand($userId, $amount, $currency, $wallet, $externalReference);
    }
}
