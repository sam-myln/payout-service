<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\IllegalStatusTransitionException;
use App\Domain\Idempotency\WebhookInboxContract;
use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStateManager;
use App\Domain\Payout\PayoutStateManagerContract;
use App\Domain\Payout\PayoutStatus;
use App\Domain\Payout\UuidGeneratorContract;
use App\Jobs\ProcessWebhookEventJob;
use App\Support\Metrics\RedisCounter;
use Carbon\CarbonImmutable;
use Money\Currency;
use Money\Money;
use Processing\CanonicalWebhookEvent;
use Tests\TestCase;

final class ProcessWebhookEventJobTest extends TestCase
{
    private Payout $payout;

    protected function setUp(): void
    {
        parent::setUp();

        $uuidGen = $this->createMock(UuidGeneratorContract::class);
        $uuidGen->method('generate')->willReturn('22222222-2222-2222-2222-222222222222');

        $this->payout = Payout::create(
            'dummy',
            42,
            new Money(200000000, new Currency('USDT')),
            'TX_webhook_test_001',
            'ref-webhook-001',
            $uuidGen
        );
    }

    public function testLegalTransitionAppliesAndMarksProcessed(): void
    {
        $this->payout->markProcessing();
        $this->payout->attachProviderId('pp-legal-001');

        $event = new CanonicalWebhookEvent(
            'evt_legal_001',
            'pp-legal-001',
            'ref-webhook-001',
            'success',
            CarbonImmutable::now()
        );

        $repo = $this->createMock(PayoutRepositoryContract::class);
        $repo->expects($this->once())
            ->method('findByProviderPayoutId')
            ->with('pp-legal-001')
            ->willReturn($this->payout);
        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Payout $p) {
                return $p->status === PayoutStatus::Success;
            }));

        $stateMachine = new PayoutStateManager();

        $inbox = $this->createMock(WebhookInboxContract::class);
        $inbox->expects($this->once())
            ->method('markProcessed')
            ->with('evt_legal_001');

        $metrics = $this->createMock(RedisCounter::class);
        $metrics->expects($this->once())
            ->method('increment')
            ->with('payout.success');

        $job = new ProcessWebhookEventJob('dummy', $event);
        $job->handle($repo, $stateMachine, $inbox, $metrics);
    }

    public function testIllegalTransitionSkipsAndMarksProcessed(): void
    {
        $this->payout->markProcessing();
        $this->payout->attachProviderId('pp-illegal-001');

        $event = new CanonicalWebhookEvent(
            'evt_illegal_001',
            'pp-illegal-001',
            'ref-webhook-001',
            'processing',
            CarbonImmutable::now()
        );

        $repo = $this->createMock(PayoutRepositoryContract::class);
        $repo->expects($this->once())
            ->method('findByProviderPayoutId')
            ->with('pp-illegal-001')
            ->willReturn($this->payout);
        $repo->expects($this->never())->method('save');

        $stateMachine = $this->createMock(PayoutStateManagerContract::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with($this->payout, PayoutStatus::Processing)
            ->willThrowException(
                IllegalStatusTransitionException::forTransition('processing', 'processing')
            );

        $inbox = $this->createMock(WebhookInboxContract::class);
        $inbox->expects($this->once())
            ->method('markProcessed')
            ->with('evt_illegal_001');

        $metrics = $this->createMock(RedisCounter::class);
        $metrics->expects($this->never())->method('increment');

        $job = new ProcessWebhookEventJob('dummy', $event);
        $job->handle($repo, $stateMachine, $inbox, $metrics);
    }

    public function testUnknownProviderPayoutIdMarksProcessedWithoutTouchingPayout(): void
    {
        $event = new CanonicalWebhookEvent(
            'evt_unknown_001',
            'pp-unknown-999',
            'ref-unknown-999',
            'success',
            CarbonImmutable::now()
        );

        $repo = $this->createMock(PayoutRepositoryContract::class);
        $repo->expects($this->once())
            ->method('findByProviderPayoutId')
            ->with('pp-unknown-999')
            ->willReturn(null);
        $repo->expects($this->never())->method('save');

        $stateMachine = $this->createMock(PayoutStateManagerContract::class);
        $stateMachine->expects($this->never())->method('transition');

        $inbox = $this->createMock(WebhookInboxContract::class);
        $inbox->expects($this->once())
            ->method('markProcessed')
            ->with('evt_unknown_001');

        $metrics = $this->createMock(RedisCounter::class);
        $metrics->expects($this->never())->method('increment');

        $job = new ProcessWebhookEventJob('dummy', $event);
        $job->handle($repo, $stateMachine, $inbox, $metrics);
    }
}
