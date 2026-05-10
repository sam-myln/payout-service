<?php declare(strict_types=1);

namespace App\Support\Metrics;

use Illuminate\Support\Facades\Redis;

class RedisCounter
{
    public function increment(string $key, int $by = 1): int
    {
        return Redis::incrby("metrics:{$key}", $by);
    }
}
