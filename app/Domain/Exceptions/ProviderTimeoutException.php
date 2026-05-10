<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class ProviderTimeoutException extends PayoutException
{
    public function __construct(
        string $message = 'Provider request timed out',
        ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Transient, $message, 0, null, $providerCode, $previous);
    }

    public static function afterSeconds(int $seconds): self
    {
        return new self("Provider request timed out after {$seconds}s");
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
