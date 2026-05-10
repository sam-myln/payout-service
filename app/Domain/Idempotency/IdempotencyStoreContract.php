<?php declare(strict_types=1);

namespace App\Domain\Idempotency;

interface IdempotencyStoreContract
{
    /** @return array{0: mixed, 1: bool} [result, wasReplay] */
    public function remember(string $key, string $fingerprint, callable $compute): array;

    /** @return array{0: mixed, 1: bool}|null [result, wasReplay] or null if not found */
    public function replay(string $key, string $fingerprint): ?array;
}
