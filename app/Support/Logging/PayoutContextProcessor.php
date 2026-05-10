<?php declare(strict_types=1);

namespace App\Support\Logging;

use Monolog\LogRecord;

final class PayoutContextProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        foreach (['payout_id', 'attempt', 'provider_status'] as $field) {
            if (isset($record->context[$field])) {
                $record->extra[$field] = $record->context[$field];
            }
        }

        return $record;
    }
}
