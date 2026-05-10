<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy;

use App\Domain\Exceptions\InvalidWebhookSignatureException;
use Carbon\CarbonImmutable;
use Processing\CanonicalWebhookEvent;
use Processing\Contracts\NotificationProcessorContract;
use Processors\PaymentProviderDummy\Api\Requests\WebhookRequest;

final class NotificationProcessor implements NotificationProcessorContract
{
    private const SIGNATURE_HEADER = 'x-provider-signature';

    /** @param array{webhook_secret: ?string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function verifyAndDecode(string $rawBody, array $headers): CanonicalWebhookEvent
    {
        $secret = $this->config['webhook_secret'] ?? null;
        if ($secret === null || $secret === '') {
            throw new InvalidWebhookSignatureException('Webhook secret not configured');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = (string) ($headers[self::SIGNATURE_HEADER] ?? '');

        if (!hash_equals($expected, $provided)) {
            throw new InvalidWebhookSignatureException();
        }

        $decoded = json_decode($rawBody, true) ?? [];

        if (isset($decoded['occurred_at']) && is_string($decoded['occurred_at'])) {
            $decoded['occurred_at'] = CarbonImmutable::parse($decoded['occurred_at']);
        }

        return WebhookRequest::validateAndCreate($decoded)->toCanonical();
    }
}
