<?php declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Validation\ValidationException;
use Processing\OutboundPaymentCommand;
use Processors\PaymentProviderDummy\Api\Requests\PaymentRequest;
use Processors\PaymentProviderDummy\Api\Requests\WebhookRequest;
use Processors\PaymentProviderDummy\Api\Responses\PaymentResponse;
use Tests\TestCase;

final class DataDtoTest extends TestCase
{
    public function testPaymentRequestFromCommand(): void
    {
        $command = new OutboundPaymentCommand(42, '150.000000', 'USDT', '0xABC123', 'ref-001');

        $dto = PaymentRequest::fromCommand($command);

        $this->assertSame(42, $dto->userId);
        $this->assertSame('150.000000', $dto->amount);
        $this->assertSame('USDT', $dto->currency);
        $this->assertSame('0xABC123', $dto->wallet);
        $this->assertSame('ref-001', $dto->externalReference);
    }

    public function testPaymentRequestInvalidAmount(): void
    {
        $this->expectException(ValidationException::class);

        PaymentRequest::validateAndCreate([
            'userId' => 42,
            'amount' => 'not-a-number',
            'currency' => 'USDT',
            'wallet' => '0xABC123',
            'externalReference' => 'ref-001',
        ]);
    }

    public function testPaymentRequestRoundTrip(): void
    {
        $data = [
            'userId' => 7,
            'amount' => '99.500000',
            'currency' => 'EUR',
            'wallet' => 'TX_WALLET',
            'externalReference' => 'ext-ref-99',
        ];

        $dto = PaymentRequest::validateAndCreate($data);
        $array = $dto->toArray();

        $this->assertSame(7, $array['userId']);
        $this->assertSame('99.500000', $array['amount']);
        $this->assertSame('EUR', $array['currency']);
        $this->assertSame('TX_WALLET', $array['wallet']);
        $this->assertSame('ext-ref-99', $array['externalReference']);
    }

    public function testPaymentResponseValid(): void
    {
        $data = [
            'provider_payout_id' => 'ppid-001',
            'status' => 'accepted',
        ];

        $dto = PaymentResponse::validateAndCreate($data);

        $this->assertSame('ppid-001', $dto->providerPayoutId);
        $this->assertSame('accepted', $dto->status);
    }

    public function testPaymentResponseInvalidStatus(): void
    {
        $this->expectException(ValidationException::class);

        PaymentResponse::validateAndCreate([
            'provider_payout_id' => 'ppid-001',
            'status' => 'bogus_status',
        ]);
    }

    public function testPaymentResponseMissingField(): void
    {
        $this->expectException(ValidationException::class);

        PaymentResponse::validateAndCreate([
            'provider_payout_id' => 'ppid-001',
        ]);
    }

    public function testPaymentResponseToResult(): void
    {
        $dto = PaymentResponse::validateAndCreate([
            'provider_payout_id' => 'ppid-roundtrip',
            'status' => 'processing',
        ]);

        $result = $dto->toResult();

        $this->assertSame('ppid-roundtrip', $result->providerPayoutId);
        $this->assertSame('processing', $result->status);
    }

    public function testWebhookRequestValid(): void
    {
        $data = [
            'event_id' => 'evt-001',
            'provider_payout_id' => 'ppid-webhook-001',
            'external_reference' => 'ref-webhook',
            'status' => 'success',
            'occurred_at' => '2025-06-15T10:30:00Z',
        ];

        $dto = WebhookRequest::validateAndCreate($data);

        $this->assertSame('evt-001', $dto->eventId);
        $this->assertSame('ppid-webhook-001', $dto->providerPayoutId);
        $this->assertSame('ref-webhook', $dto->externalReference);
        $this->assertSame('success', $dto->status);
        $this->assertSame('2025-06-15T10:30:00Z', $dto->occurredAt->toIso8601ZuluString());
    }

    public function testWebhookRequestInvalidStatus(): void
    {
        $this->expectException(ValidationException::class);

        WebhookRequest::validateAndCreate([
            'event_id' => 'evt-001',
            'provider_payout_id' => 'ppid-001',
            'external_reference' => 'ref-001',
            'status' => 'unknown_status',
            'occurred_at' => '2025-06-15T10:30:00Z',
        ]);
    }

    public function testWebhookRequestMissingField(): void
    {
        $this->expectException(ValidationException::class);

        WebhookRequest::validateAndCreate([
            'event_id' => 'evt-001',
            'provider_payout_id' => 'ppid-001',
            'external_reference' => 'ref-001',
        ]);
    }

    public function testWebhookRequestToCanonical(): void
    {
        $dto = WebhookRequest::validateAndCreate([
            'event_id' => 'evt-roundtrip',
            'provider_payout_id' => 'ppid-rt-001',
            'external_reference' => 'ref-rt',
            'status' => 'processing',
            'occurred_at' => '2025-01-01T00:00:00Z',
        ]);

        $canonical = $dto->toCanonical();

        $this->assertSame('evt-roundtrip', $canonical->eventId);
        $this->assertSame('ppid-rt-001', $canonical->providerPayoutId);
        $this->assertSame('ref-rt', $canonical->externalReference);
        $this->assertSame('processing', $canonical->status);
        $this->assertSame('2025-01-01T00:00:00Z', $canonical->occurredAt->toIso8601ZuluString());
    }
}
