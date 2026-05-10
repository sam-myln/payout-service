<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use RuntimeException;
use Throwable;

final class ProviderValidationException extends PayoutException
{
    public function __construct(
        string $message = 'Provider rejected request as invalid',
        ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Terminal, $message, 0, null, $providerCode, $previous);
    }

    public static function fromStatusCode(int $statusCode, string $body = ''): self
    {
        $message = "Provider rejected request with HTTP {$statusCode}";

        return new self($message, previous: new RuntimeException(substr($body, 0, 500)));
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %s [classification=%s, providerCode=%s]',
            self::class,
            $this->message,
            $this->classification()->value,
            $this->providerCode() ?? 'null'
        );
    }
}
