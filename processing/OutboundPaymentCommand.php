<?php declare(strict_types=1);

namespace Processing;

use Processing\Contracts\PaymentCommandContract;

final readonly class OutboundPaymentCommand implements PaymentCommandContract
{
    public function __construct(
        private int $userId,
        private string $amount,
        private string $currency,
        private string $wallet,
        private string $externalReference
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getWallet(): string
    {
        return $this->wallet;
    }

    public function getExternalReference(): string
    {
        return $this->externalReference;
    }
}
