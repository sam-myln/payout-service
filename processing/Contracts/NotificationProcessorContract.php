<?php declare(strict_types=1);

namespace Processing\Contracts;

use Processing\CanonicalWebhookEvent;

interface NotificationProcessorContract
{
    /**
     * Verify the inbound webhook signature against the raw request body and decode
     * it into a canonical event. Throws InvalidWebhookSignatureException on failure.
     * @param array<string, string> $headers Lowercased header name => value.
     */
    public function verifyAndDecode(string $rawBody, array $headers): CanonicalWebhookEvent;
}
