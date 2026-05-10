<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\IllegalStatusTransitionException;
use App\Domain\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Exceptions\PayoutNotFoundException;
use App\Domain\Exceptions\ProviderContractViolationException;
use App\Domain\Exceptions\ProviderNetworkException;
use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderRejectedException;
use App\Domain\Exceptions\ProviderTimeoutException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Domain\Retry\Classification;
use PHPUnit\Framework\TestCase;

final class ExceptionClassificationTest extends TestCase
{
    public static function transientExceptions(): array
    {
        return [
            'rate limited' => [new ProviderRateLimitedException()],
            'unavailable' => [new ProviderUnavailableException()],
            'timeout' => [new ProviderTimeoutException()],
            'network' => [new ProviderNetworkException()],
        ];
    }

    /** @dataProvider transientExceptions */
    public function testTransientExceptionsClassifyAsTransient(object $exception): void
    {
        $this->assertSame(Classification::Transient, $exception->classification());
    }

    public static function terminalExceptions(): array
    {
        return [
            'validation' => [new ProviderValidationException()],
            'rejected' => [new ProviderRejectedException()],
            'contract violation' => [new ProviderContractViolationException()],
            'not found' => [new PayoutNotFoundException()],
            'idempotency conflict' => [new IdempotencyConflictException()],
            'invalid signature' => [new InvalidWebhookSignatureException()],
            'illegal transition' => [new IllegalStatusTransitionException()],
        ];
    }

    /** @dataProvider terminalExceptions */
    public function testTerminalExceptionsClassifyAsTerminal(object $exception): void
    {
        $this->assertSame(Classification::Terminal, $exception->classification());
    }

    public function testRateLimitedCarriesRetryAfter(): void
    {
        $e = new ProviderRateLimitedException(60);

        $this->assertSame(60, $e->retryAfter());
    }

    public function testRateLimitedFromHeader(): void
    {
        $e = ProviderRateLimitedException::fromHeader(30);

        $this->assertSame(30, $e->retryAfter());
        $this->assertSame(Classification::Transient, $e->classification());
        $this->assertStringContainsString('30s', $e->getMessage());
    }

    public function testPayoutNotFoundForUuid(): void
    {
        $e = PayoutNotFoundException::forUuid('abc-123');

        $this->assertStringContainsString('abc-123', $e->getMessage());
        $this->assertSame(Classification::Terminal, $e->classification());
    }

    public function testIdempotencyConflictForKey(): void
    {
        $e = IdempotencyConflictException::forKey('key-abc');

        $this->assertStringContainsString('key-abc', $e->getMessage());
        $this->assertSame(Classification::Terminal, $e->classification());
    }

    public function testIllegalTransitionForTransition(): void
    {
        $e = IllegalStatusTransitionException::forTransition('pending', 'success');

        $this->assertStringContainsString('pending', $e->getMessage());
        $this->assertStringContainsString('success', $e->getMessage());
        $this->assertSame(Classification::Terminal, $e->classification());
    }

    public function testContractViolationCarriesRawBody(): void
    {
        $raw = '{"garbage": true}';
        $e = ProviderContractViolationException::fromRawBody($raw);

        $this->assertSame($raw, $e->rawBody());
        $this->assertSame(Classification::Terminal, $e->classification());
    }
}
