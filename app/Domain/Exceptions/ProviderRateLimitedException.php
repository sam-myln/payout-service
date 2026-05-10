<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class ProviderRateLimitedException extends PayoutException
{
    public function __construct(
        ?int $retryAfter = null,
        string $message = 'Provider rate limited',
        ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Transient, $message, 429, $retryAfter, $providerCode, $previous);
    }

    public static function fromHeader(int $retryAfter): self
    {
        return new self($retryAfter, "Provider rate limited, retry after {$retryAfter}s");
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %s [classification=%s, retryAfter=%d]',
            self::class,
            $this->message,
            $this->classification()->value,
            $this->retryAfter() ?? 0
        );
    }
}
