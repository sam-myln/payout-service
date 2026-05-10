<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\ProviderContractViolationException;
use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Support\Metrics\RedisCounter;
use Illuminate\Support\Facades\Http;
use Processing\OutboundPaymentCommand;
use Processors\PaymentProviderDummy\PaymentProcessor;
use Tests\TestCase;

final class HttpProviderClientTest extends TestCase
{
    private function makeProcessor(): PaymentProcessor
    {
        return new PaymentProcessor(
            ['base_url' => 'https://provider.example'],
            $this->app->make(RedisCounter::class)
        );
    }

    private function validCommand(): OutboundPaymentCommand
    {
        return new OutboundPaymentCommand(
            1,
            '100.000000',
            'USDT',
            '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045',
            'ref-001'
        );
    }

    public function test202ValidBodyReturnsPaymentResult(): void
    {
        Http::fake([
            'provider.example/*' => Http::response([
                'provider_payout_id' => 'ppid-202-001',
                'status' => 'accepted',
            ], 202),
        ]);

        $result = $this->makeProcessor()->send($this->validCommand());

        $this->assertSame('ppid-202-001', $result->providerPayoutId);
        $this->assertSame('accepted', $result->status);
    }

    public function test200MissingProviderPayoutIdThrowsContractViolation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response(['status' => 'accepted'], 200),
        ]);

        $this->expectException(ProviderContractViolationException::class);
        $this->makeProcessor()->send($this->validCommand());
    }

    public function test200WithStatusWeirdUnknownThrowsContractViolation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response([
                'provider_payout_id' => 'ppid-weird',
                'status' => 'weird_unknown',
            ], 200),
        ]);

        $this->expectException(ProviderContractViolationException::class);
        $this->makeProcessor()->send($this->validCommand());
    }

    public function test429WithRetryAfter30ThrowsRateLimited(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('', 429, ['Retry-After' => '30']),
        ]);

        try {
            $this->makeProcessor()->send($this->validCommand());
            $this->fail('Expected ProviderRateLimitedException');
        } catch (ProviderRateLimitedException $e) {
            $this->assertSame(30, $e->retryAfter());
        }
    }

    public function test500ThrowsUnavailable(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(ProviderUnavailableException::class);
        $this->makeProcessor()->send($this->validCommand());
    }

    public function test400ThrowsValidation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('{"error":"bad_request"}', 400),
        ]);

        $this->expectException(ProviderValidationException::class);
        $this->makeProcessor()->send($this->validCommand());
    }
}
