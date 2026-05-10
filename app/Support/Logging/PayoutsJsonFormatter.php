<?php declare(strict_types=1);

namespace App\Support\Logging;

use JsonException;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

final class PayoutsJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $output = json_decode(parent::format($record), true, 512, \JSON_THROW_ON_ERROR);

        foreach (['request_id', 'payout_id', 'attempt', 'provider_status'] as $field) {
            if (isset($record->context[$field])) {
                $output[$field] = $record->context[$field];
            }
        }

        $encoded = json_encode($output, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new JsonException('Failed to encode log record as JSON');
        }

        return $encoded."\n";
    }
}
