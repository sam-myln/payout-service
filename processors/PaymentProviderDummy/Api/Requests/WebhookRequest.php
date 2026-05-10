<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy\Api\Requests;

use Carbon\CarbonImmutable;
use Processing\CanonicalWebhookEvent;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class WebhookRequest extends Data
{
    public function __construct(
        #[Required]
        public string $eventId,
        #[Required]
        public string $providerPayoutId,
        #[Required]
        public string $externalReference,
        #[Required]
        #[In(['processing', 'success', 'failed'])]
        public string $status,
        #[Required]
        public CarbonImmutable $occurredAt
    ) {
    }

    public function toCanonical(): CanonicalWebhookEvent
    {
        return new CanonicalWebhookEvent(
            $this->eventId,
            $this->providerPayoutId,
            $this->externalReference,
            $this->status,
            $this->occurredAt
        );
    }
}
