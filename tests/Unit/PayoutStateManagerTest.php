<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Exceptions\IllegalStatusTransitionException;
use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutStateManager;
use App\Domain\Payout\PayoutStatus;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class PayoutStateManagerTest extends TestCase
{
    private PayoutStateManager $sm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sm = new PayoutStateManager();
    }

    private function payout(PayoutStatus $status): Payout
    {
        return new Payout(
            1,
            (string) Str::uuid(),
            'dummy',
            1,
            new Money(100000000, new Currency('USDT')),
            'TX001',
            'ref_001',
            $status,
            'pp_001',
            null,
            0,
            null,
            null,
            null
        );
    }

    public function testPendingToProcessingIsLegal(): void
    {
        $payout = $this->payout(PayoutStatus::Pending);

        $this->sm->transition($payout, PayoutStatus::Processing);

        $this->assertSame(PayoutStatus::Processing, $payout->status);
    }

    public function testProcessingToSuccessIsLegal(): void
    {
        $payout = $this->payout(PayoutStatus::Processing);

        $this->sm->transition($payout, PayoutStatus::Success);

        $this->assertSame(PayoutStatus::Success, $payout->status);
    }

    public function testProcessingToFailedIsLegal(): void
    {
        $payout = $this->payout(PayoutStatus::Processing);

        $this->sm->transition($payout, PayoutStatus::Failed);

        $this->assertSame(PayoutStatus::Failed, $payout->status);
    }

    public function testProcessingToProcessingIsNoop(): void
    {
        $payout = $this->payout(PayoutStatus::Processing);

        $this->sm->transition($payout, PayoutStatus::Processing);

        $this->assertSame(PayoutStatus::Processing, $payout->status);
    }

    public function testSuccessToProcessingIsIllegal(): void
    {
        $payout = $this->payout(PayoutStatus::Success);

        $this->expectException(IllegalStatusTransitionException::class);

        $this->sm->transition($payout, PayoutStatus::Processing);
    }

    public function testFailedToProcessingIsIllegal(): void
    {
        $payout = $this->payout(PayoutStatus::Failed);

        $this->expectException(IllegalStatusTransitionException::class);

        $this->sm->transition($payout, PayoutStatus::Processing);
    }

    public function testPendingToSuccessIsIllegal(): void
    {
        $payout = $this->payout(PayoutStatus::Pending);

        $this->expectException(IllegalStatusTransitionException::class);

        $this->sm->transition($payout, PayoutStatus::Success);
    }

    public function testSuccessToFailedIsIllegal(): void
    {
        $payout = $this->payout(PayoutStatus::Success);

        $this->expectException(IllegalStatusTransitionException::class);

        $this->sm->transition($payout, PayoutStatus::Failed);
    }

    public function testFailedToSuccessIsIllegal(): void
    {
        $payout = $this->payout(PayoutStatus::Failed);

        $this->expectException(IllegalStatusTransitionException::class);

        $this->sm->transition($payout, PayoutStatus::Success);
    }

    public function testCanTransitionReturnsCorrectValues(): void
    {
        $this->assertTrue($this->sm->canTransition(PayoutStatus::Pending, PayoutStatus::Processing));
        $this->assertFalse($this->sm->canTransition(PayoutStatus::Pending, PayoutStatus::Success));
        $this->assertTrue($this->sm->canTransition(PayoutStatus::Processing, PayoutStatus::Processing));
        $this->assertTrue($this->sm->canTransition(PayoutStatus::Processing, PayoutStatus::Success));
        $this->assertTrue($this->sm->canTransition(PayoutStatus::Processing, PayoutStatus::Failed));
        $this->assertFalse($this->sm->canTransition(PayoutStatus::Success, PayoutStatus::Processing));
        $this->assertFalse($this->sm->canTransition(PayoutStatus::Failed, PayoutStatus::Processing));
    }
}
