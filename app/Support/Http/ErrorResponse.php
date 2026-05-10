<?php declare(strict_types=1);

namespace App\Support\Http;

final readonly class ErrorResponse
{
    public function __construct(
        public string $code,
        public string $message,
        public string $requestId,
        public ?array $details = null
    ) {
    }

    public static function notFound(string $requestId): self
    {
        return new self('payout_not_found', 'Payout not found', $requestId);
    }

    public static function validation(array $errors, string $requestId): self
    {
        return new self('validation_failed', 'Validation failed', $requestId, ['errors' => $errors]);
    }

    public static function idempotencyConflict(string $requestId): self
    {
        return new self(
            'idempotency_conflict',
            'A request with this idempotency key is already in progress',
            $requestId
        );
    }

    public static function invalidSignature(string $requestId): self
    {
        return new self('invalid_signature', 'HMAC signature verification failed', $requestId);
    }

    public static function internal(string $requestId): self
    {
        return new self('internal_error', 'An unexpected error occurred', $requestId);
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
                'request_id' => $this->requestId,
                ...($this->details ? ['details' => $this->details] : []),
            ],
        ];
    }
}
