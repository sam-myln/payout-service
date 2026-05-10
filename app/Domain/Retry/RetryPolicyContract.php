<?php declare(strict_types=1);

namespace App\Domain\Retry;

use Throwable;

interface RetryPolicyContract
{
    public function classify(Throwable $e): Classification;

    public function nextDelaySeconds(int $attempt, ?int $retryAfter): int;

    public function maxAttempts(): int;
}
