<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class IllegalStatusTransitionException extends PayoutException
{
    public function __construct(string $message = 'Illegal status transition', ?Throwable $previous = null)
    {
        parent::__construct(Classification::Terminal, $message, 0, null, null, $previous);
    }

    public static function forTransition(string $from, string $to): self
    {
        return new self("Illegal status transition from {$from} to {$to}");
    }
}
