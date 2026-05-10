<?php declare(strict_types=1);

namespace App\Domain\Payout;

interface PayoutStateManagerContract
{
    public function transition(Payout $payout, PayoutStatus $target): void;

    public function canTransition(PayoutStatus $from, PayoutStatus $to): bool;
}
