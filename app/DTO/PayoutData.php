<?php declare(strict_types=1);

namespace App\DTO;

use App\Domain\Payout\Payout;
use Carbon\CarbonImmutable;
use Money\MoneyFormatter;
use Spatie\LaravelData\Data;

final class PayoutData extends Data
{
    public function __construct(
        public string $uuid,
        public int $userId,
        public string $amount,
        public string $currency,
        public string $wallet,
        public string $externalReference,
        public string $status,
        public int $attempts,
        public ?string $lastError,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt
    ) {
    }

    public static function fromDomain(Payout $payout): self
    {
        return new self(
            $payout->uuid,
            $payout->userId,
            app(MoneyFormatter::class)->format($payout->money),
            $payout->money->getCurrency()->getCode(),
            $payout->wallet,
            $payout->externalReference,
            $payout->status->value,
            $payout->attempts,
            $payout->lastError,
            $payout->createdAt ? CarbonImmutable::instance($payout->createdAt) : null,
            $payout->updatedAt ? CarbonImmutable::instance($payout->updatedAt) : null
        );
    }
}
