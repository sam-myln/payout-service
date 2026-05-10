<?php declare(strict_types=1);

namespace App\Domain\Payout;

use App\Domain\Exceptions\IllegalStatusTransitionException;

final class PayoutStateManager implements PayoutStateManagerContract
{
    private const TRANSITIONS = [
        PayoutStatus::Pending->value => [PayoutStatus::Processing->value],
        PayoutStatus::Processing->value => [
            PayoutStatus::Processing->value,
            PayoutStatus::Success->value,
            PayoutStatus::Failed->value,
        ],
    ];

    public function transition(Payout $payout, PayoutStatus $target): void
    {
        if (!$this->canTransition($payout->status, $target)) {
            throw IllegalStatusTransitionException::forTransition($payout->status->value, $target->value);
        }

        if ($payout->status === $target) {
            return;
        }

        $payout->status = $target;
    }

    public function canTransition(PayoutStatus $from, PayoutStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }
}
