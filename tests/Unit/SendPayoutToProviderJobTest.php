<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStatus;
use App\Domain\Payout\UuidGeneratorContract;
use App\Domain\Retry\ExponentialBackoffRetryPolicy;
use App\Domain\Retry\RetryPolicyContract;
use App\Jobs\SendPayoutToProviderJob;
use App\Services\PayoutService;
use App\Support\Metrics\RedisCounter;
use Money\Currency;
use Money\Money;
use Money\MoneyFormatter;
use Processing\Contracts\PaymentProcessorContract;
use Processing\Contracts\ProcessorFactoryContract;
use Processing\Contracts\ProcessorRegistryContract;
use Processing\OutboundPaymentCommand;
use Processing\OutboundPaymentCommandFactory;
use Processing\PaymentResult;
use Tests\TestCase;

final class SendPayoutToProviderJobTest extends TestCase
{
    private Payout $payout;
    private RetryPolicyContract $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ExponentialBackoffRetryPolicy();

        $uuidGen = $this->createMock(UuidGeneratorContract::class);
        $uuidGen->method('generate')->willReturn('11111111-1111-1111-1111-111111111111');

        $this->payout = Payout::create(
            'dummy',
            42,
            new Money(150000000, new Currency('USDT')),
            'TX1234567890',
            'ref-job-test-001',
            $uuidGen
        );
    }

    private function makeRepoMock(): PayoutRepositoryContract
    {
        $repo = $this->createMock(PayoutRepositoryContract::class);
        $repo->method('find')->with($this->payout->uuid)->willReturn($this->payout);
        $repo->method('save');

        return $repo;
    }

    private function makePayoutService(PayoutRepositoryContract $serviceRepo): PayoutService
    {
        return new PayoutService(
            $serviceRepo,
            $this->createMock(UuidGeneratorContract::class)
        );
    }

    private function makePartialJob(): SendPayoutToProviderJob
    {
        return $this->getMockBuilder(SendPayoutToProviderJob::class)
            ->setConstructorArgs([$this->payout->uuid])
            ->onlyMethods(['release'])
            ->getMock();
    }

    private function makeMetricsMock(array &$calls, int $expectedCount): RedisCounter
    {
        $metrics = $this->createMock(RedisCounter::class);
        $metrics->expects($this->exactly($expectedCount))
            ->method('increment')
            ->willReturnCallback(static function (string $key) use (&$calls) {
                $calls[] = $key;

                return 1;
            });

        return $metrics;
    }

    private function makeMoneyFormatter(): MoneyFormatter
    {
        $f = $this->createMock(MoneyFormatter::class);
        $f->method('format')->willReturn('150.000000');

        return $f;
    }

    private function makeRegistryMock(PaymentProcessorContract $processor): ProcessorRegistryContract
    {
        $factory = $this->createMock(ProcessorFactoryContract::class);
        $factory->method('makePayment')->willReturn($processor);

        $registry = $this->createMock(ProcessorRegistryContract::class);
        $registry->method('factoryFor')->with('dummy')->willReturn($factory);

        return $registry;
    }

    private function makeCommandFactory(): OutboundPaymentCommandFactory
    {
        return new OutboundPaymentCommandFactory();
    }

    public function testTransientExceptionReleasesJobAndIncrementsAttempts(): void
    {
        $processor = $this->createMock(PaymentProcessorContract::class);
        $processor->method('send')
            ->with($this->isInstanceOf(OutboundPaymentCommand::class))
            ->willThrowException(new ProviderUnavailableException());

        $registry = $this->makeRegistryMock($processor);

        $repo = $this->makeRepoMock();
        $payoutService = $this->makePayoutService($this->createMock(PayoutRepositoryContract::class));

        $metricCalls = [];
        $metrics = $this->makeMetricsMock($metricCalls, 1);

        $job = $this->makePartialJob();
        $job->expects($this->once())->method('release')->with($this->isType('int'));

        $job->handle(
            $registry,
            $this->policy,
            $payoutService,
            $metrics,
            $repo,
            $this->makeMoneyFormatter(),
            $this->makeCommandFactory()
        );

        $this->assertSame(1, $this->payout->attempts);
        $this->assertSame(PayoutStatus::Processing, $this->payout->status);
        $this->assertSame(['payout.attempts'], $metricCalls);
    }

    public function testTerminalExceptionMarksFailedDoesNotRelease(): void
    {
        $exception = new ProviderValidationException('Bad wallet format', 'BAD_WALLET');

        $processor = $this->createMock(PaymentProcessorContract::class);
        $processor->method('send')->willThrowException($exception);

        $registry = $this->makeRegistryMock($processor);

        $repo = $this->makeRepoMock();
        $payoutService = $this->makePayoutService($this->createMock(PayoutRepositoryContract::class));

        $metricCalls = [];
        $metrics = $this->makeMetricsMock($metricCalls, 2);

        $job = $this->makePartialJob();
        $job->expects($this->never())->method('release');

        $job->handle(
            $registry,
            $this->policy,
            $payoutService,
            $metrics,
            $repo,
            $this->makeMoneyFormatter(),
            $this->makeCommandFactory()
        );

        $this->assertSame(PayoutStatus::Failed, $this->payout->status);
        $this->assertStringContainsString('BAD_WALLET', $this->payout->lastError ?? '');
        $this->assertStringContainsString('Bad wallet format', $this->payout->lastError ?? '');
        $this->assertSame(['payout.attempts', 'payout.failed'], $metricCalls);
    }

    public function testSuccessfulDispatchSavesProviderIdAndDoesNotTouchSuccessMetric(): void
    {
        $result = new PaymentResult('pp-provider-xyz', 'accepted');

        $processor = $this->createMock(PaymentProcessorContract::class);
        $processor->method('send')->willReturn($result);

        $registry = $this->makeRegistryMock($processor);

        $repo = $this->makeRepoMock();
        $payoutService = $this->makePayoutService($this->createMock(PayoutRepositoryContract::class));

        $metricCalls = [];
        $metrics = $this->makeMetricsMock($metricCalls, 2);

        $job = $this->makePartialJob();
        $job->expects($this->never())->method('release');

        $job->handle(
            $registry,
            $this->policy,
            $payoutService,
            $metrics,
            $repo,
            $this->makeMoneyFormatter(),
            $this->makeCommandFactory()
        );

        $this->assertSame('pp-provider-xyz', $this->payout->providerPayoutId);
        $this->assertSame(PayoutStatus::Processing, $this->payout->status);
        $this->assertSame(['payout.attempts', 'payout.dispatched'], $metricCalls);
        $this->assertNotContains('payout.success', $metricCalls);
    }

    public function testAttemptsExceededDoesNotRelease(): void
    {
        $this->payout->attempts = 9;

        $processor = $this->createMock(PaymentProcessorContract::class);
        $processor->method('send')->willThrowException(new ProviderUnavailableException());

        $registry = $this->makeRegistryMock($processor);

        $repo = $this->makeRepoMock();
        $payoutService = $this->makePayoutService($this->createMock(PayoutRepositoryContract::class));

        $metricCalls = [];
        $metrics = $this->makeMetricsMock($metricCalls, 2);

        $job = $this->makePartialJob();
        $job->expects($this->never())->method('release');

        $job->handle(
            $registry,
            $this->policy,
            $payoutService,
            $metrics,
            $repo,
            $this->makeMoneyFormatter(),
            $this->makeCommandFactory()
        );

        $this->assertSame(PayoutStatus::Failed, $this->payout->status);
        $this->assertSame(10, $this->payout->attempts);
        $this->assertSame(['payout.attempts', 'payout.failed'], $metricCalls);
    }
}
