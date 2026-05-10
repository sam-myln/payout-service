<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use RuntimeException;
use Throwable;

final class ProviderNetworkException extends PayoutException
{
    public function __construct(
        string $message = 'Provider network error',
        ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Transient, $message, 0, null, $providerCode, $previous);
    }

    public static function fromCurlError(int $errno, string $error): self
    {
        return new self("Network error [{$errno}]: {$error}", previous: new RuntimeException($error));
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
