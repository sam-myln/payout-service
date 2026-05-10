<?php declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Payout\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class PayoutModel extends Model
{
    protected $table = 'payouts';

    protected $fillable = [
        'uuid',
        'provider',
        'user_id',
        'amount_minor',
        'currency',
        'wallet',
        'external_reference',
        'status',
        'provider_payout_id',
        'idempotency_key',
        'attempts',
        'last_error',
        'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'last_attempted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        self::creating(static function (self $model): void {
            $model->uuid ??= (string) Str::orderedUuid();
        });
    }
}
