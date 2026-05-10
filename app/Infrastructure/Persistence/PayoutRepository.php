<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Money\Currency;
use Money\Money;
use RuntimeException;

final class PayoutRepository implements PayoutRepositoryContract
{
    public function save(Payout $payout): void
    {
        $model = $payout->id !== null
            ? PayoutModel::find($payout->id)
            : new PayoutModel();

        if ($model === null) {
            throw new RuntimeException("Payout not found for id={$payout->id}");
        }

        $model->fill([
            'uuid' => $payout->uuid,
            'provider' => $payout->provider,
            'user_id' => $payout->userId,
            'amount_minor' => $payout->money->getAmount(),
            'currency' => $payout->money->getCurrency()->getCode(),
            'wallet' => $payout->wallet,
            'external_reference' => $payout->externalReference,
            'status' => $payout->status,
            'provider_payout_id' => $payout->providerPayoutId,
            'idempotency_key' => $payout->idempotencyKey,
            'attempts' => $payout->attempts,
            'last_error' => $payout->lastError,
        ]);

        $model->save();

        $payout->id = $model->id;
        $payout->createdAt = $model->created_at instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($model->created_at)
            : null;
        $payout->updatedAt = $model->updated_at instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($model->updated_at)
            : null;
    }

    public function find(string $uuid): ?Payout
    {
        $model = PayoutModel::where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdempotencyKey(string $key): ?Payout
    {
        $model = PayoutModel::where('idempotency_key', $key)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProviderPayoutId(string $id): ?Payout
    {
        $model = PayoutModel::where('provider_payout_id', $id)->first();

        return $model ? $this->toDomain($model) : null;
    }

    private function toDomain(PayoutModel $model): Payout
    {
        $payout = new Payout(
            $model->id,
            $model->uuid,
            (string) $model->provider,
            $model->user_id,
            new Money($model->amount_minor, new Currency($model->currency)),
            $model->wallet,
            $model->external_reference,
            $model->status,
            $model->provider_payout_id,
            $model->idempotency_key,
            (int) $model->attempts,
            $model->last_error,
            $model->created_at instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($model->created_at)
                : null,
            $model->updated_at instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($model->updated_at)
                : null
        );

        if ($payout->status instanceof PayoutStatus === false) {
            $payout->status = PayoutStatus::from((string) $model->getRawOriginal('status'));
        }

        return $payout;
    }
}
