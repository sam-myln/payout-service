<?php declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\LogRecord;

final class RequestIdProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $sharedContext = Log::sharedContext();

        if (isset($sharedContext['request_id']) && !isset($record->context['request_id'])) {
            return $record->with(
                context: array_merge($record->context, ['request_id' => $sharedContext['request_id']])
            );
        }

        return $record;
    }
}
