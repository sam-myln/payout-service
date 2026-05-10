<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Domain\Retry\Classification;
use App\Domain\Retry\ExponentialBackoffRetryPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmokeRetryPolicyCommandTest extends TestCase
{
    private ExponentialBackoffRetryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ExponentialBackoffRetryPolicy();
    }

    public function testNextDelayFirstAttempt(): void
    {
        $delay = $this->policy->nextDelaySeconds(1, null);

        $this->assertGreaterThanOrEqual(0, $delay);
        $this->assertLessThanOrEqual(1, $delay);
    }

    public function testNextDelayFifthAttempt(): void
    {
        $delay = $this->policy->nextDelaySeconds(5, null);

        $this->assertGreaterThanOrEqual(0, $delay);
        $this->assertLessThanOrEqual(16, $delay);
    }

    public function testNextDelayWithRetryAfter(): void
    {
        $delay = $this->policy->nextDelaySeconds(3, 60);

        $this->assertLessThanOrEqual(60, $delay);
    }

    public function testClassifyTransient(): void
    {
        $c = $this->policy->classify(new ProviderUnavailableException());

        $this->assertSame(Classification::Transient, $c);
    }

    public function testClassifyTerminalPayout(): void
    {
        $c = $this->policy->classify(new ProviderValidationException());

        $this->assertSame(Classification::Terminal, $c);
    }

    public function testClassifyUnknownExceptionIsTerminal(): void
    {
        $c = $this->policy->classify(new RuntimeException('unknown'));

        $this->assertSame(Classification::Terminal, $c);
    }

    public function testClassifyProviderRateLimitedExceptionIsTransient(): void
    {
        $c = $this->policy->classify(new ProviderRateLimitedException(30));

        $this->assertSame(Classification::Transient, $c);
    }
}
