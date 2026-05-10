<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy\Api\Responses;

use Processing\PaymentResult;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class PaymentResponse extends Data
{
    public const ALLOWED_STATUSES = ['accepted', 'processing', 'success', 'failed'];

    public function __construct(
        #[Required]
        public string $providerPayoutId,
        #[Required]
        #[In(self::ALLOWED_STATUSES)]
        public string $status
    ) {
    }

    public function toResult(): PaymentResult
    {
        return new PaymentResult($this->providerPayoutId, $this->status);
    }
}
