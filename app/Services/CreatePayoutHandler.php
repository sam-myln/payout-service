<?php declare(strict_types=1);

namespace App\Services;

use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\DTO\CreatePayoutCommand;
use App\Jobs\SendPayoutToProviderJob;

final class CreatePayoutHandler
{
    public function __construct(private PayoutService $payoutService, private PayoutRepositoryContract $repository)
    {
    }

    public function handle(CreatePayoutCommand $command): Payout
    {
        if ($command->idempotencyKey !== null) {
            $existing = $this->repository->findByIdempotencyKey($command->idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $payout = $this->payoutService->create($command);

        SendPayoutToProviderJob::dispatch($payout->uuid)->onQueue('payouts');

        return $payout;
    }
}
