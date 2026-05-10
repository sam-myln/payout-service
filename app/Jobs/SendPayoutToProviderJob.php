<?php declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Exceptions\PayoutException;
use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStatus;
use App\Domain\Retry\Classification;
use App\Domain\Retry\RetryPolicyContract;
use App\Services\PayoutService;
use App\Support\Metrics\RedisCounter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Money\MoneyFormatter;
use Processing\Contracts\ProcessorRegistryContract;
use Processing\OutboundPaymentCommandFactory;
use Throwable;

class SendPayoutToProviderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $maxExceptions = 10;

    private string $provider;

    public function __construct(private readonly string $payoutUuid)
    {
    }

    public function handle(
        ProcessorRegistryContract $registry,
        RetryPolicyContract $policy,
        PayoutService $payoutService,
        RedisCounter $metrics,
        PayoutRepositoryContract $repo,
        MoneyFormatter $moneyFormatter,
        OutboundPaymentCommandFactory $outboundPaymentCommandFactory
    ): void {
        $payout = $repo->find($this->payoutUuid);

        if ($payout === null) {
            Log::error('payout.not_found', ['payout_uuid' => $this->payoutUuid]);

            return;
        }

        $this->provider = $payout->provider;

        if (!in_array($payout->status, [PayoutStatus::Pending, PayoutStatus::Processing], true)) {
            Log::info('payout.already_terminal', [
                'payout_uuid' => $this->payoutUuid,
                'status' => $payout->status->value,
            ]);

            return;
        }

        $payoutService->markProcessing($payout);

        $metrics->increment('payout.attempts');

        try {
            $decimalAmount = $moneyFormatter->format($payout->money);

            $command = $outboundPaymentCommandFactory->create(
                $payout->userId,
                $decimalAmount,
                $payout->money->getCurrency()->getCode(),
                $payout->wallet,
                $payout->externalReference
            );

            $processor = $registry->factoryFor($this->provider)->makePayment();
            $result = $processor->send($command);

            $payoutService->attachProviderId($payout, $result->providerPayoutId);

            Log::info('payout.dispatched', [
                'payout_uuid' => $payout->uuid,
                'provider' => $this->provider,
                'provider_payout_id' => $result->providerPayoutId,
            ]);

            $metrics->increment('payout.dispatched');
        } catch (PayoutException $e) {
            $class = $policy->classify($e);
            $payout->incrementAttempts();

            if ($class === Classification::Transient && $payout->attempts < $policy->maxAttempts()) {
                $retryAfter = $e instanceof ProviderRateLimitedException
                    ? $e->retryAfter()
                    : null;
                $delay = $policy->nextDelaySeconds($payout->attempts, $retryAfter);

                Log::info('payout.retrying', [
                    'payout_uuid' => $payout->uuid,
                    'attempt' => $payout->attempts,
                    'delay' => $delay,
                    'exception' => $e->__toString(),
                ]);

                $repo->save($payout);
                $this->release($delay);
            } else {
                $errorCode = $e->providerCode() ?? 'provider_error';

                $payoutService->markFailed($payout, $errorCode, $e->getMessage());

                $metrics->increment('payout.failed');

                Log::error('payout.failed', [
                    'payout_uuid' => $payout->uuid,
                    'error_code' => $errorCode,
                    'message' => $e->getMessage(),
                    'attempts' => $payout->attempts,
                ]);
            }
        } catch (Throwable $e) {
            $payout->incrementAttempts();

            $payoutService->markFailed($payout, 'unexpected_error', $e::class.': '.$e->getMessage());

            $metrics->increment('payout.failed');
            $metrics->increment('payout.unexpected');

            Log::error('payout.unexpected', [
                'payout_uuid' => $payout->uuid,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'previous' => $e->getPrevious()?->getMessage(),
            ]);
        }
    }
}
