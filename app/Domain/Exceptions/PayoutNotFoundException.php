<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class PayoutNotFoundException extends PayoutException
{
    public function __construct(string $message = 'Payout not found', ?Throwable $previous = null)
    {
        parent::__construct(Classification::Terminal, $message, 0, null, null, $previous);
    }

    public static function forUuid(string $uuid): self
    {
        return new self("Payout not found: {$uuid}");
    }
}
