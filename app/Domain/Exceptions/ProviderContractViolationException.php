<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Retry\Classification;
use Throwable;

final class ProviderContractViolationException extends PayoutException
{
    private string $rawBody;

    /**
     * 200/202 with malformed body is treated as TERMINAL — provider has
     * misbehaved, retry won't help. Log full body so we can detect new
     * undocumented response shapes. If frequency rises, escalate to add
     * new known-status to the allowlist or contact provider.
     */
    public function __construct(
        string $message = 'Provider response violated the contract',
        ?string $rawBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(Classification::Terminal, $message, 0, null, null, $previous);

        $this->rawBody = $rawBody ?? '';
    }

    public static function fromRawBody(string $rawBody, ?Throwable $previous = null): self
    {
        $preview = mb_substr($rawBody, 0, 200);

        return new self("Provider contract violation, body preview: {$preview}", $rawBody, $previous);
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function __toString(): string
    {
        $preview = mb_substr($this->rawBody, 0, 500);

        return sprintf(
            '%s: %s [classification=%s, rawBody=%s]',
            self::class,
            $this->message,
            $this->classification()->value,
            $preview
        );
    }
}
