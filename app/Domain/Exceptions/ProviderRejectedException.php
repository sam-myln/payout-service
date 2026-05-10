<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class ProviderRejectedException extends PayoutException
{
    public function __construct(
        string $message = 'Provider explicitly rejected the payout',
        ?string $providerCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Terminal, $message, 0, null, $providerCode, $previous);
    }

    public static function fromProvider(string $code, string $message): self
    {
        return new self("Provider rejected payout [{$code}]: {$message}", $code);
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
