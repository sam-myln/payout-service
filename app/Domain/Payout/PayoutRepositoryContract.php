<?php declare(strict_types=1);

namespace App\Domain\Payout;

interface PayoutRepositoryContract
{
    public function save(Payout $payout): void;

    public function find(string $uuid): ?Payout;

    public function findByIdempotencyKey(string $key): ?Payout;

    public function findByProviderPayoutId(string $id): ?Payout;
}
