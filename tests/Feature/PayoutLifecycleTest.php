<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PayoutLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'lifecycle-test-secret-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('providers.enabled', ['dummy']);
        config()->set('providers.dummy', [
            // TODO
            'base_url' => 'http://provider.test',
            'webhook_secret' => $this->secret,
            'timeout_connect' => 3,
            'timeout_read' => 3,
        ]);
        config()->set('queue.default', 'database');
        config()->set('cache.default', 'array');
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }

    public function testFullLifecycleWithSuccessWebhook(): void
    {
        Http::fake([
            'provider.test/provider/payouts' => Http::response([
                'provider_payout_id' => 'pp-lifecycle-success-001',
                'status' => 'accepted',
            ], 200),
        ]);

        $start = microtime(true);

        $response = $this->postJson('/api/payouts', [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => 'TX_lifecycle_001',
            'externalReference' => 'ref-lifecycle-success',
        ]);

        $response->assertStatus(202);
        $payoutUuid = $response->json('data.uuid');
        $this->assertIsString($payoutUuid);
        $this->assertSame(36, strlen($payoutUuid));

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();

        $this->assertNotNull($payout);
        $this->assertSame('pending', $payout->status);

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'payouts']);

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();

        $this->assertSame('pp-lifecycle-success-001', $payout->provider_payout_id);
        $this->assertSame('processing', $payout->status);

        $webhookBody = json_encode([
            'event_id' => 'evt_lifecycle_success_001',
            'provider_payout_id' => 'pp-lifecycle-success-001',
            'external_reference' => 'ref-lifecycle-success',
            'status' => 'success',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $signature = $this->sign($webhookBody);

        $webhookResp = $this->call('POST', '/api/webhooks/dummy', [], [], [], [
            'HTTP_X-Provider-Signature' => $signature,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], $webhookBody);

        $webhookResp->assertStatus(200);

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'webhooks']);

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();
        $this->assertSame('success', $payout->status);

        $duration = microtime(true) - $start;
        $this->assertLessThan(30, $duration, 'Integration test took too long');
    }

    public function testFullLifecycleWithRateLimitOnce(): void
    {
        Http::fake([
            'provider.test/provider/payouts' => Http::sequence()
                ->push('', 429, ['Retry-After' => '1'])
                ->push([
                    'provider_payout_id' => 'pp-lifecycle-ratelimit-001',
                    'status' => 'accepted',
                ], 200),
        ]);

        $start = microtime(true);

        $response = $this->postJson('/api/payouts', [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '200.00',
            'currency' => 'USDT',
            'wallet' => 'TX_lifecycle_rl_001',
            'externalReference' => 'ref-lifecycle-ratelimit',
        ]);

        $response->assertStatus(202);
        $payoutUuid = $response->json('data.uuid');

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'payouts']);

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();
        $this->assertSame(1, (int)$payout->attempts);
        $this->assertNull($payout->provider_payout_id);
        $this->assertSame('processing', $payout->status);

        sleep(2);

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'payouts']);

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();
        $this->assertSame('pp-lifecycle-ratelimit-001', $payout->provider_payout_id);
        $this->assertSame('processing', $payout->status);

        $webhookBody = json_encode([
            'event_id' => 'evt_lifecycle_rl_001',
            'provider_payout_id' => 'pp-lifecycle-ratelimit-001',
            'external_reference' => 'ref-lifecycle-ratelimit',
            'status' => 'success',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $signature = $this->sign($webhookBody);

        $this->call('POST', '/api/webhooks/dummy', [], [], [], [
            'HTTP_X-Provider-Signature' => $signature,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], $webhookBody)
            ->assertStatus(200);

        Artisan::call('queue:work', ['--once' => true, '--queue' => 'webhooks']);

        $payout = DB::table('payouts')->where('uuid', $payoutUuid)->first();
        $this->assertSame('success', $payout->status);

        $duration = microtime(true) - $start;
        $this->assertLessThan(30, $duration, 'Integration test took too long');
    }
}
