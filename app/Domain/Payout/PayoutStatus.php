<?php declare(strict_types=1);

namespace App\Domain\Payout;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}
