<?php declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Idempotency\IdempotencyStoreContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

final class RedisIdempotencyStore implements IdempotencyStoreContract
{
    private const TTL = 86400;
    private const KEY_PREFIX = 'idem:payouts:';

    public function remember(string $key, string $fingerprint, callable $compute): array
    {
        $redisKey = self::KEY_PREFIX.$key;

        $claimed = Redis::set(
            $redisKey,
            json_encode([
                'fingerprint' => $fingerprint,
                'state' => 'in_flight',
            ]),
            'EX',
            self::TTL,
            'NX'
        );

        if ($claimed) {
            return $this->executeAndStore($redisKey, $fingerprint, $compute);
        }

        $existing = $this->loadExisting(Redis::get($redisKey));

        if ($existing === null) {
            return $this->executeAndStore($redisKey, $fingerprint, $compute);
        }

        if ($existing['state'] === 'done') {
            if ($existing['fingerprint'] === $fingerprint) {
                return [$this->reconstructResponse($existing), true];
            }

            throw IdempotencyConflictException::forKey($key);
        }

        if ($existing['fingerprint'] === $fingerprint) {
            return $this->executeAndStore($redisKey, $fingerprint, $compute);
        }

        throw IdempotencyConflictException::forKey($key);
    }

    public function replay(string $key, string $fingerprint): ?array
    {
        $existing = $this->loadExisting(Redis::get(self::KEY_PREFIX.$key));

        if ($existing === null) {
            return null;
        }

        if ($existing['state'] === 'done' && $existing['fingerprint'] === $fingerprint) {
            return [$this->reconstructResponse($existing), true];
        }

        return null;
    }

    private function executeAndStore(string $redisKey, string $fingerprint, callable $compute): array
    {
        $result = $compute();

        if ($result instanceof JsonResponse) {
            Redis::set(
                $redisKey,
                json_encode([
                    'fingerprint' => $fingerprint,
                    'state' => 'done',
                    'status' => $result->getStatusCode(),
                    'body' => $result->getContent(),
                ]),
                'EX',
                self::TTL
            );
        }

        return [$result, false];
    }

    /** @return array{state: string, fingerprint: string, status?: int, body?: string}|null */
    private function loadExisting(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['state'], $decoded['fingerprint'])) {
            return null;
        }

        return $decoded;
    }

    /** @param array{state: string, fingerprint: string, status: int, body: string} $stored */
    private function reconstructResponse(array $stored): JsonResponse
    {
        return new JsonResponse(
            json_decode($stored['body'], true),
            $stored['status']
        );
    }
}
