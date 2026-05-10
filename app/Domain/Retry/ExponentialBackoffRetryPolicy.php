<?php declare(strict_types=1);

namespace App\Domain\Retry;

use App\Domain\Exceptions\PayoutException;
use Throwable;

final class ExponentialBackoffRetryPolicy implements RetryPolicyContract
{
    private const int MAX_ATTEMPS = 10;

    public function __construct(private readonly int $baseSeconds = 1, private readonly int $capSeconds = 300)
    {
    }

    public function maxAttempts(): int
    {
        return self::MAX_ATTEMPS;
    }

    public function classify(Throwable $e): Classification
    {
        if ($e instanceof PayoutException) {
            return $e->classification();
        }

        return Classification::Terminal;
    }

    public function nextDelaySeconds(int $attempt, ?int $retryAfter): int
    {
        if ($retryAfter !== null) {
            return min($retryAfter, $this->capSeconds);
        }

        $attempt = min($attempt, 30);

        $computed = min($this->capSeconds, $this->baseSeconds * (1 << ($attempt - 1)));

        return random_int(0, $computed);
    }
}
