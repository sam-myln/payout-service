<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy\Api\Requests;

use Processing\OutboundPaymentCommand;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class PaymentRequest extends Data
{
    public function __construct(
        #[Required]
        public int $userId,
        #[Required]
        #[Regex('/^\d+\.\d{2,8}$/')]
        public string $amount,
        #[Required]
        #[In(['USDT', 'USD', 'EUR'])]
        public string $currency,
        #[Required]
        #[Max(128)]
        public string $wallet,
        #[Required]
        #[Max(128)]
        public string $externalReference
    ) {
    }

    public static function fromCommand(OutboundPaymentCommand $command): self
    {
        return new self(
            $command->getUserId(),
            $command->getAmount(),
            $command->getCurrency(),
            $command->getWallet(),
            $command->getExternalReference()
        );
    }
}
