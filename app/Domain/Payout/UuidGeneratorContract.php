<?php declare(strict_types=1);

namespace App\Domain\Payout;

interface UuidGeneratorContract
{
    public function generate(): string;
}
