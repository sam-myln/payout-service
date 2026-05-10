<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class CreatePayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
    }

    public function testHappyPathReturns202WithPayoutUuid(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/payouts', [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-happy-001',
        ]);

        $response->assertStatus(202);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertIsString($data['uuid']);
        $this->assertSame(36, strlen($data['uuid']));
        $this->assertArrayNotHasKey('id', $data);

        $this->assertDatabaseCount('payouts', 1);
    }

    public function testIdempotencyReplaySameBodyReturns202SameUuid(): void
    {
        Queue::fake();

        $payload = [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-replay-001',
        ];
        $key = 'idem-replay-001';

        $first = $this->postJson('/api/payouts', $payload, ['Idempotency-Key' => $key]);
        $first->assertStatus(202);
        $firstUuid = $first->json('data.uuid');
        $this->assertNotNull($firstUuid);

        $second = $this->postJson('/api/payouts', $payload, ['Idempotency-Key' => $key]);
        $second->assertStatus(202);
        $this->assertSame($firstUuid, $second->json('data.uuid'));

        $this->assertDatabaseCount('payouts', 1);
    }

    public function testIdempotencyConflictDifferentBodyReturns409(): void
    {
        Queue::fake();

        $payload1 = [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-conflict-1a',
        ];
        $payload2 = [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '200.00',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-conflict-1b',
        ];
        $key = 'idem-conflict-001';

        $this->postJson('/api/payouts', $payload1, ['Idempotency-Key' => $key])
            ->assertStatus(202);

        $second = $this->postJson('/api/payouts', $payload2, ['Idempotency-Key' => $key]);
        $second->assertStatus(409);
        $second->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function testMissingWalletReturns422(): void
    {
        $response = $this->postJson('/api/payouts', [
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'externalReference' => 'ref-no-wallet',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
    }

    public function testInvalidAmountFormatReturns422(): void
    {
        $response = $this->postJson('/api/payouts', [
            'userId' => 42,
            'amount' => 'abc',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-bad-amount',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
    }

    public function testNoIdempotencyKeyCreatesTwoPayouts(): void
    {
        Queue::fake();

        $payload = [
            'provider' => 'dummy',
            'userId' => 42,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => 'TX1234567890',
            'externalReference' => 'ref-no-key',
        ];

        $first = $this->postJson('/api/payouts', $payload);
        $first->assertStatus(202);

        $second = $this->postJson('/api/payouts', $payload);
        $second->assertStatus(202);

        $this->assertNotSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('payouts', 2);
    }
}
