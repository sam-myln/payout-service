<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Exceptions\ProviderContractViolationException;
use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Processing\Contracts\ProcessorRegistryContract;
use Processing\OutboundPaymentCommand;
use Tests\TestCase;

final class ProviderClientTest extends TestCase
{
    private const BASE_URL = 'https://provider.example';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('providers.enabled', ['dummy']);
        config()->set('providers.dummy', [
            'base_url' => self::BASE_URL,
            'webhook_secret' => 'secret',
        ]);
    }

    private function makeProcessor()
    {
        $registry = $this->app->make(ProcessorRegistryContract::class);

        return $registry->factoryFor('dummy')->makePayment();
    }

    private function command(string $ref): OutboundPaymentCommand
    {
        return new OutboundPaymentCommand(1, '100.000000', 'USDT', '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', $ref);
    }

    public function testHttpProviderSuccess202(): void
    {
        Http::fake([
            'provider.example/*' => Http::response([
                'provider_payout_id' => 'ppid-hs-001',
                'status' => 'accepted',
            ], 202),
        ]);

        $result = $this->makeProcessor()->send($this->command('ref-http-202'));

        $this->assertSame('ppid-hs-001', $result->providerPayoutId);
        $this->assertSame('accepted', $result->status);
    }

    public function testHttpProviderSuccess200(): void
    {
        Http::fake([
            'provider.example/*' => Http::response([
                'provider_payout_id' => 'ppid-hs-200',
                'status' => 'processing',
            ], 200),
        ]);

        $result = $this->makeProcessor()->send($this->command('ref-http-200'));

        $this->assertSame('ppid-hs-200', $result->providerPayoutId);
        $this->assertSame('processing', $result->status);
    }

    public function testHttpProvider429ThrowsRateLimited(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('', 429, ['Retry-After' => '30']),
        ]);

        try {
            $this->makeProcessor()->send($this->command('ref-http-429'));
            $this->fail('Expected ProviderRateLimitedException');
        } catch (ProviderRateLimitedException $e) {
            $this->assertSame(30, $e->retryAfter());
        }
    }

    public function testHttpProvider5xxThrowsUnavailable(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('Service Unavailable', 503),
        ]);

        $this->expectException(ProviderUnavailableException::class);
        $this->makeProcessor()->send($this->command('ref-http-503'));
    }

    public function testHttpProvider502ThrowsUnavailable(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('Bad Gateway', 502),
        ]);

        $this->expectException(ProviderUnavailableException::class);
        $this->makeProcessor()->send($this->command('ref-http-502'));
    }

    public function testHttpProvider4xxNon429ThrowsValidation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('{"error":"bad_request"}', 400),
        ]);

        $this->expectException(ProviderValidationException::class);
        $this->makeProcessor()->send($this->command('ref-http-400'));
    }

    public function testHttpProvider404ThrowsValidation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('Not Found', 404),
        ]);

        $this->expectException(ProviderValidationException::class);
        $this->makeProcessor()->send($this->command('ref-http-404'));
    }

    public function testHttpProviderMalformedJsonThrowsContractViolation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('not-valid-json{{{', 200),
        ]);

        $this->expectException(ProviderContractViolationException::class);
        $this->makeProcessor()->send($this->command('ref-http-malformed-json'));
    }

    public function testHttpProviderWrongShapeThrowsContractViolation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('{"provider_payout_id":"ppid-bad","status":"bogus_status"}', 200),
        ]);

        $this->expectException(ProviderContractViolationException::class);
        $this->makeProcessor()->send($this->command('ref-http-wrong-shape'));
    }

    public function testHttpProviderMissingFieldThrowsContractViolation(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('{"provider_payout_id":"ppid-missing-status"}', 200),
        ]);

        $this->expectException(ProviderContractViolationException::class);
        $this->makeProcessor()->send($this->command('ref-http-missing-field'));
    }

    public function testHttpProvider429SendsIntegerSecondsRetryAfter(): void
    {
        Http::fake([
            'provider.example/*' => Http::response('', 429, ['Retry-After' => '60']),
        ]);

        try {
            $this->makeProcessor()->send($this->command('ref-429-int'));
            $this->fail('Expected ProviderRateLimitedException');
        } catch (ProviderRateLimitedException $e) {
            $this->assertSame(60, $e->retryAfter());
        }
    }

    public function testUnknownProviderSlugThrows(): void
    {
        $registry = $this->app->make(ProcessorRegistryContract::class);

        $this->expectException(InvalidArgumentException::class);

        $registry->factoryFor('bogus');
    }
}
