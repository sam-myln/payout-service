<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class IdempotencyConflictException extends PayoutException
{
    public function __construct(string $message = 'Idempotency key conflict', ?Throwable $previous = null)
    {
        parent::__construct(Classification::Terminal, $message, 0, null, null, $previous);
    }

    public static function forKey(string $key): self
    {
        return new self("Idempotency key conflict for key: {$key}");
    }
}
