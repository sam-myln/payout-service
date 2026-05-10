<?php declare(strict_types=1);

namespace App\Domain\Idempotency;

interface WebhookInboxContract
{
    public function recordOrIgnore(string $eventId, string $providerPayoutId, string $payload, string $signature): bool;

    public function markProcessed(string $eventId): void;
}
