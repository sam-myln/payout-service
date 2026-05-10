<?php declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Exceptions\IllegalStatusTransitionException;
use App\Domain\Idempotency\WebhookInboxContract;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStateManagerContract;
use App\Domain\Payout\PayoutStatus;
use App\Support\Metrics\RedisCounter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Processing\CanonicalWebhookEvent;

final class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $provider, private readonly CanonicalWebhookEvent $event)
    {
    }

    public function handle(
        PayoutRepositoryContract $repo,
        PayoutStateManagerContract $payoutStateManager,
        WebhookInboxContract $inbox,
        RedisCounter $metrics
    ): void {
        $payout = $repo->findByProviderPayoutId($this->event->providerPayoutId);

        if ($payout === null) {
            Log::warning('webhook.unknown_payout', [
                'event_id' => $this->event->eventId,
                'provider' => $this->provider,
                'provider_payout_id' => $this->event->providerPayoutId,
            ]);

            $inbox->markProcessed($this->event->eventId);

            return;
        }

        $targetStatus = PayoutStatus::from($this->event->status);

        try {
            $payoutStateManager->transition($payout, $targetStatus);
        } catch (IllegalStatusTransitionException) {
            Log::warning('webhook.illegal_transition', [
                'event_id' => $this->event->eventId,
                'provider' => $this->provider,
                'provider_payout_id' => $this->event->providerPayoutId,
                'from' => $payout->status->value,
                'to' => $targetStatus->value,
            ]);

            $inbox->markProcessed($this->event->eventId);

            return;
        }

        $repo->save($payout);

        if ($targetStatus === PayoutStatus::Success) {
            $metrics->increment('payout.success');
        } elseif ($targetStatus === PayoutStatus::Failed) {
            $metrics->increment('payout.failed');
        }

        $inbox->markProcessed($this->event->eventId);
    }
}
