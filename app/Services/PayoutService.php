<?php declare(strict_types=1);

namespace App\Services;

use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\UuidGeneratorContract;
use App\DTO\CreatePayoutCommand;
use Illuminate\Database\QueryException;

final class PayoutService
{
    public function __construct(
        private readonly PayoutRepositoryContract $repository,
        private readonly UuidGeneratorContract $uuidGenerator
    ) {
    }

    public function create(CreatePayoutCommand $command): Payout
    {
        $payout = Payout::create(
            $command->provider,
            $command->userId,
            $command->money,
            $command->wallet,
            $command->externalReference,
            $this->uuidGenerator,
            $command->idempotencyKey
        );

        try {
            $this->repository->save($payout);
        } catch (QueryException $e) {
            if ($command->idempotencyKey !== null && str_contains($e->getMessage(), 'idempotency_key')) {
                return $this->repository->findByIdempotencyKey($command->idempotencyKey)
                    ?? throw $e;
            }

            throw $e;
        }

        return $payout;
    }

    public function markProcessing(Payout $payout): void
    {
        $payout->markProcessing();
        $this->repository->save($payout);
    }

    public function attachProviderId(Payout $payout, string $providerPayoutId): void
    {
        $payout->attachProviderId($providerPayoutId);
        $this->repository->save($payout);
    }

    public function markFailed(Payout $payout, string $code, string $msg): void
    {
        $payout->markFailed($code, $msg);
        $this->repository->save($payout);
    }

    public function markSuccess(Payout $payout): void
    {
        $payout->markSuccess();
        $this->repository->save($payout);
    }
}
