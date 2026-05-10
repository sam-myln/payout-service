<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class InvalidWebhookSignatureException extends PayoutException
{
    public function __construct(string $message = 'Invalid webhook signature', ?Throwable $previous = null)
    {
        parent::__construct(Classification::Terminal, $message, 0, null, null, $previous);
    }
}
