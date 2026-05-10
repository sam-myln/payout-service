<?php declare(strict_types=1);

namespace App\DTO;

use App\DTO\Casts\MoneyCast;
use App\DTO\Transformers\MoneyTransformer;
use Illuminate\Http\Request;
use Money\Money;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

final class CreatePayoutCommand extends Data
{
    public function __construct(
        public string $provider,
        public int $userId,
        #[WithCast(MoneyCast::class)]
        #[WithTransformer(MoneyTransformer::class)]
        public Money $money,
        public string $wallet,
        public string $externalReference,
        public ?string $idempotencyKey = null
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return self::validateAndCreate([
            ...$request->all(),
            'idempotencyKey' => $request->header('Idempotency-Key'),
        ]);
    }

    public static function prepareForPipeline(array $properties): array
    {
        if (!array_key_exists('money', $properties)) {
            $properties['money'] = [
                'amount' => $properties['amount'] ?? null,
                'currency' => $properties['currency'] ?? null,
            ];
        }

        return $properties;
    }

    public static function rules(ValidationContext $context): array
    {
        return [
            'provider' => ['required', 'string', 'in:'.implode(',', (array) config('providers.enabled', []))],
            'userId' => ['required', 'integer'],
            'money' => ['required', 'array'],
            'money.amount' => ['required', 'regex:/^\d+\.\d{2,8}$/'],
            'money.currency' => ['required',],
            'wallet' => ['required', 'string', 'max:128'],
            'externalReference' => ['required', 'string', 'max:128'],
            'idempotencyKey' => ['nullable', 'string'],
        ];
    }
}
