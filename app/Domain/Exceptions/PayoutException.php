<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use RuntimeException;
use Throwable;

abstract class PayoutException extends RuntimeException
{
    public function __construct(
        private Classification $classification,
        string $message = '',
        int $code = 0,
        private ?int $retryAfter = null,
        private ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function classification(): Classification
    {
        return $this->classification;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function providerCode(): ?string
    {
        return $this->providerCode;
    }

    public function __toString(): string
    {
        return sprintf('%s: %s [classification=%s]', static::class, $this->message, $this->classification->value);
    }
}
