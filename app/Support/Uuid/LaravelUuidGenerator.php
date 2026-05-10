<?php declare(strict_types=1);

namespace App\Support\Uuid;

use App\Domain\Payout\UuidGeneratorContract;
use Illuminate\Support\Str;

final class LaravelUuidGenerator implements UuidGeneratorContract
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
