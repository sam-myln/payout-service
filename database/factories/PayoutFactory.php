<?php declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Payout\PayoutStatus;
use App\Infrastructure\Persistence\PayoutModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class PayoutFactory extends Factory
{
    protected $model = PayoutModel::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::orderedUuid(),
            'provider' => 'dummy',
            'user_id' => fake()->numberBetween(1, 10000),
            'amount_minor' => fake()->numberBetween(1000, 100000000),
            'currency' => 'USDT',
            'wallet' => fake()->regexify('[A-Za-z0-9]{32}'),
            'external_reference' => 'ref_'.fake()->uuid(),
            'status' => PayoutStatus::Pending->value,
            'provider_payout_id' => null,
            'idempotency_key' => null,
            'attempts' => 0,
            'last_error' => null,
            'last_attempted_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(static fn() => ['status' => PayoutStatus::Pending->value]);
    }

    public function processing(): static
    {
        return $this->state(static fn() => ['status' => PayoutStatus::Processing->value]);
    }

    public function success(): static
    {
        return $this->state(static fn() => ['status' => PayoutStatus::Success->value]);
    }

    public function failed(): static
    {
        return $this->state(static fn() => ['status' => PayoutStatus::Failed->value]);
    }

    public function withProviderId(string $providerPayoutId): static
    {
        return $this->state(static fn() => ['provider_payout_id' => $providerPayoutId]);
    }

    public function withIdempotencyKey(string $key): static
    {
        return $this->state(static fn() => ['idempotency_key' => $key]);
    }
}
