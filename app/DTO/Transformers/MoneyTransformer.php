<?php declare(strict_types=1);

namespace App\DTO\Transformers;

use Money\Money;
use Money\MoneyFormatter;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final class MoneyTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (!$value instanceof Money) {
            return $value;
        }

        return app(MoneyFormatter::class)->format($value);
    }
}
