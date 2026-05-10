<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderTimeoutException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Domain\Retry\Classification;
use App\Domain\Retry\ExponentialBackoffRetryPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExponentialBackoffRetryPolicyTest extends TestCase
{
    private ExponentialBackoffRetryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ExponentialBackoffRetryPolicy(1, 300);
    }

    public function testBackoffMonotonicUpToCap(): void
    {
        $prev = -1;
        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $upper = 1 << ($attempt - 1);
            for ($i = 0; $i < 50; $i++) {
                $delay = $this->policy->nextDelaySeconds($attempt, null);
                $this->assertGreaterThanOrEqual(0, $delay);
                $this->assertLessThanOrEqual(min(300, $upper), $delay);
            }

            $prev = min(300, $upper);
        }
    }

    public function testRetryAfterHonoredWhenPresentAndBelowCap(): void
    {
        $delay = $this->policy->nextDelaySeconds(3, 30);

        $this->assertSame(30, $delay);
    }

    public function testRetryAfterClampedToCapWhenAboveCap(): void
    {
        $delay = $this->policy->nextDelaySeconds(1, 900);

        $this->assertSame(300, $delay);
    }

    public function testRetryAfterAtCap(): void
    {
        $delay = $this->policy->nextDelaySeconds(1, 300);

        $this->assertSame(300, $delay);
    }

    public function testNextDelayJitterBoundsForAttempt3(): void
    {
        $bounds = [0, 4];

        for ($i = 0; $i < 100; $i++) {
            $delay = $this->policy->nextDelaySeconds(3, null);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(4, $delay);
        }
    }

    public function testNextDelayAtCapUpperLimit(): void
    {
        $policy = new ExponentialBackoffRetryPolicy(1, 10);

        for ($i = 0; $i < 100; $i++) {
            $delay = $policy->nextDelaySeconds(5, null);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(10, $delay);
        }
    }

    public function testAttemptCeilingAt30PreventsOverflow(): void
    {
        $policy = new ExponentialBackoffRetryPolicy(1, 3600);

        for ($i = 0; $i < 20; $i++) {
            $delay = $policy->nextDelaySeconds(40, null);
            $this->assertGreaterThanOrEqual(0, $delay);
            $this->assertLessThanOrEqual(1 << 29, $delay);
        }
    }

    public function testClassifyTransientException(): void
    {
        $e = new ProviderRateLimitedException();

        $classification = $this->policy->classify($e);

        $this->assertSame(Classification::Transient, $classification);
    }

    public function testClassifyTerminalException(): void
    {
        $e = new ProviderValidationException();

        $classification = $this->policy->classify($e);

        $this->assertSame(Classification::Terminal, $classification);
    }

    public function testClassifyUnknownException(): void
    {
        $e = new RuntimeException('something else');

        $classification = $this->policy->classify($e);

        $this->assertSame(Classification::Terminal, $classification);
    }

    public function testClassifyEachExceptionType(): void
    {
        $transient = [
            new ProviderRateLimitedException(),
            new ProviderTimeoutException(),
            new ProviderUnavailableException(),
        ];
        foreach ($transient as $e) {
            $this->assertSame(Classification::Transient, $this->policy->classify($e), $e::class);
        }

        $terminal = [new ProviderValidationException()];
        foreach ($terminal as $e) {
            $this->assertSame(Classification::Terminal, $this->policy->classify($e), $e::class);
        }
    }
}
