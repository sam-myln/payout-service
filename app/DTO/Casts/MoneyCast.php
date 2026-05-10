<?php declare(strict_types=1);

namespace App\DTO\Casts;

use Money\Currency;
use Money\Money;
use Money\MoneyParser;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

final class MoneyCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if ($value instanceof Money) {
            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $amount = (string) ($value['amount'] ?? '');
        $currency = (string) ($value['currency'] ?? '');

        return app(MoneyParser::class)->parse($amount, new Currency($currency));
    }
}
