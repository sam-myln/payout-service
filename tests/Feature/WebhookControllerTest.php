<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStatus;
use App\Domain\Payout\UuidGeneratorContract;
use App\Jobs\ProcessWebhookEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Money\Currency;
use Money\Money;
use Tests\TestCase;

final class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('providers.enabled', ['dummy']);
        config()->set('providers.dummy', [
            'webhook_secret' => $this->secret,
        ]);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }

    private function webhookBody(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_'.bin2hex(random_bytes(8)),
            'provider_payout_id' => 'pp_'.bin2hex(random_bytes(8)),
            'external_reference' => 'ref_001',
            'status' => 'success',
            'occurred_at' => now()->toIso8601String(),
        ], $overrides);
    }

    public function testValidSignatureAndNewEventDispatchesJob(): void
    {
        Queue::fake();

        $body = $this->webhookBody();
        $json = json_encode($body);
        $signature = $this->sign($json);

        $response = $this->withHeaders(['X-Provider-Signature' => $signature])
            ->postJson('/api/webhooks/dummy', $body);

        $response->assertStatus(200);

        Queue::assertPushed(ProcessWebhookEventJob::class, 1);
        Queue::assertPushedOn('webhooks', ProcessWebhookEventJob::class);
    }

    public function testDuplicateEventIdDoesNotDispatchSecondJob(): void
    {
        Queue::fake();

        $body = $this->webhookBody(['event_id' => 'evt_duplicate_001']);
        $json = json_encode($body);
        $signature = $this->sign($json);

        $this->withHeaders(['X-Provider-Signature' => $signature])
            ->postJson('/api/webhooks/dummy', $body)
            ->assertStatus(200);

        Queue::assertPushed(ProcessWebhookEventJob::class, 1);

        $this->withHeaders(['X-Provider-Signature' => $signature])
            ->postJson('/api/webhooks/dummy', $body)
            ->assertStatus(200);

        Queue::assertPushed(ProcessWebhookEventJob::class, 1);
    }

    public function testBadSignatureReturns401(): void
    {
        Queue::fake();

        $body = $this->webhookBody();

        $response = $this->withHeaders(['X-Provider-Signature' => 'bad-signature-0000'])
            ->postJson('/api/webhooks/dummy', $body);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'invalid_signature');

        Queue::assertNothingPushed();
    }

    public function testRawBodyKeyOrderPreserved(): void
    {
        Queue::fake();

        $ts = now()->toIso8601String();
        $rawBody = '{"z_key":"first","provider_payout_id":"pp_rawbody_001","a_key":"last",'
            .'"event_id":"evt_rawbody_001","external_reference":"ref_001",'
            .'"status":"success","occurred_at":"'.$ts.'"}';
        $signature = $this->sign($rawBody);

        $response = $this->call('POST', '/api/webhooks/dummy', [], [], [], [
            'HTTP_X-Provider-Signature' => $signature,
            'HTTP_CONTENT_TYPE' => 'application/json',
        ], $rawBody);

        $response->assertStatus(200);
        Queue::assertPushed(ProcessWebhookEventJob::class, 1);
    }

    public function testUnknownProviderPayoutIdMarksWebhookProcessed(): void
    {
        config()->set('queue.default', 'sync');

        $body = $this->webhookBody(['provider_payout_id' => 'pp_unknown_999']);
        $json = json_encode($body);
        $signature = $this->sign($json);

        $response = $this->withHeaders(['X-Provider-Signature' => $signature])
            ->postJson('/api/webhooks/dummy', $body);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => $body['event_id'],
            'provider_payout_id' => 'pp_unknown_999',
        ]);

        $row = DB::table('webhook_events')
            ->where('event_id', $body['event_id'])
            ->first();

        $this->assertNotNull($row->processed_at);
    }

    public function testIllegalTransitionLogsAndSkips(): void
    {
        config()->set('queue.default', 'sync');

        $uuidGenerator = $this->app->make(UuidGeneratorContract::class);
        $repo = $this->app->make(PayoutRepositoryContract::class);

        $providerPayoutId = 'pp_illegal_'.bin2hex(random_bytes(4));

        $payout = Payout::create(
            'dummy',
            1,
            new Money(100000000, new Currency('USDT')),
            'TX_illegal_001',
            'ref_illegal',
            $uuidGenerator
        );
        $payout->markProcessing();
        $payout->attachProviderId($providerPayoutId);
        $payout->markSuccess();
        $repo->save($payout);

        $body = $this->webhookBody([
            'provider_payout_id' => $providerPayoutId,
            'status' => 'processing',
        ]);
        $json = json_encode($body);
        $signature = $this->sign($json);

        $response = $this->withHeaders(['X-Provider-Signature' => $signature])
            ->postJson('/api/webhooks/dummy', $body);

        $response->assertStatus(200);

        $payout = $repo->find($payout->uuid);
        $this->assertSame(PayoutStatus::Success, $payout->status);
    }
}
