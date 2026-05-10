<?php declare(strict_types=1);

namespace Processing;

use Carbon\CarbonImmutable;

final readonly class CanonicalWebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $providerPayoutId,
        public string $externalReference,
        public string $status,
        public CarbonImmutable $occurredAt
    ) {
    }
}
