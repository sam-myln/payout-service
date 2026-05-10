<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Idempotency\RedisIdempotencyStore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class RedisIdempotencyStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushdb();
    }

    public function testFirstWriteStoresSecondReadReplays(): void
    {
        $store = new RedisIdempotencyStore();
        $key = 'test-key-001';
        $fingerprint = 'sha256_abc123';
        $computedResponse = response()->json(['uuid' => 'abc-123'], 202);

        [$result, $wasReplay] = $store->remember($key, $fingerprint, static function () use ($computedResponse) {
            return $computedResponse;
        });

        $this->assertFalse($wasReplay);
        $this->assertSame(202, $result->getStatusCode());

        [$result2, $wasReplay2] = $store->remember($key, $fingerprint, function () {
            $this->fail('Should not call compute on replay');
        });

        $this->assertTrue($wasReplay2);
        $this->assertSame(202, $result2->getStatusCode());
    }

    public function testMismatchedFingerprintSurfacesConflict(): void
    {
        $store = new RedisIdempotencyStore();
        $key = 'test-key-conflict';
        $fingerprint1 = 'sha256_abc123';
        $fingerprint2 = 'sha256_xyz789';

        $store->remember($key, $fingerprint1, static function () {
            return response()->json(['uuid' => 'abc-123'], 202);
        });

        $this->expectException(IdempotencyConflictException::class);
        $store->remember($key, $fingerprint2, static function () {
            return response()->json(['uuid' => 'xyz-789'], 202);
        });
    }

    public function testTtlApplied(): void
    {
        $store = new RedisIdempotencyStore();
        $key = 'test-key-ttl';
        $fingerprint = 'sha256_ttl_test';

        $store->remember($key, $fingerprint, static function () {
            return response()->json(['uuid' => 'ttl-001'], 202);
        });

        $ttl = Redis::ttl('idem:payouts:'.$key);

        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(86400, $ttl);
    }

    public function testReplayReturnsNullWhenNotFound(): void
    {
        $store = new RedisIdempotencyStore();

        $result = $store->replay('nonexistent-key', 'any-fingerprint');

        $this->assertNull($result);
    }

    public function testReplayReturnsResultWhenFound(): void
    {
        $store = new RedisIdempotencyStore();
        $key = 'test-key-replay';
        $fingerprint = 'sha256_replay';

        $store->remember($key, $fingerprint, static function () {
            return response()->json(['uuid' => 'replay-001'], 202);
        });

        $result = $store->replay($key, $fingerprint);

        $this->assertNotNull($result);
        [$response, $wasReplay] = $result;
        $this->assertTrue($wasReplay);
        $this->assertSame(202, $response->getStatusCode());
    }
}
