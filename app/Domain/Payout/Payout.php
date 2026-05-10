<?php declare(strict_types=1);

namespace App\Domain\Payout;

use DateTimeImmutable;
use Money\Money;

final class Payout
{
    public function __construct(
        public ?int $id,
        public string $uuid,
        public string $provider,
        public int $userId,
        public Money $money,
        public string $wallet,
        public string $externalReference,
        public PayoutStatus $status,
        public ?string $providerPayoutId,
        public ?string $idempotencyKey,
        public int $attempts,
        public ?string $lastError,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(
        string $provider,
        int $userId,
        Money $money,
        string $wallet,
        string $externalReference,
        UuidGeneratorContract $uuidGenerator,
        ?string $idempotencyKey = null
    ): self {
        return new self(
            null,
            $uuidGenerator->generate(),
            $provider,
            $userId,
            $money,
            $wallet,
            $externalReference,
            PayoutStatus::Pending,
            null,
            $idempotencyKey,
            0,
            null,
            null,
            null
        );
    }

    public function markProcessing(): void
    {
        $this->status = PayoutStatus::Processing;
    }

    public function attachProviderId(string $providerPayoutId): void
    {
        $this->providerPayoutId = $providerPayoutId;
    }

    public function markFailed(string $errorCode, string $errorMessage): void
    {
        $this->status = PayoutStatus::Failed;
        $this->lastError = $errorCode.': '.$errorMessage;
    }

    public function markSuccess(): void
    {
        $this->status = PayoutStatus::Success;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }
}
