<?php declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Domain\Idempotency\WebhookInboxContract;
use Illuminate\Support\Facades\DB;

final class EloquentWebhookInbox implements WebhookInboxContract
{
    public function recordOrIgnore(string $eventId, string $providerPayoutId, string $payload, string $signature): bool
    {
        return DB::table('webhook_events')->insertOrIgnore([
            'event_id' => $eventId,
            'provider_payout_id' => $providerPayoutId,
            'payload' => $payload,
            'signature' => $signature,
            'received_at' => now(),
        ]) > 0;
    }

    public function markProcessed(string $eventId): void
    {
        DB::table('webhook_events')
            ->where('event_id', $eventId)
            ->update(['processed_at' => now()]);
    }
}
