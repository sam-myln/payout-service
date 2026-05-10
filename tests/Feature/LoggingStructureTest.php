<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Logging\PayoutsJsonFormatter;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

final class LoggingStructureTest extends TestCase
{
    private TestHandler $testHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testHandler = new TestHandler();

        $logger = Log::channel('payouts')->getLogger();
        $logger->setHandlers([$this->testHandler]);
    }

    public function testLogRecordContainsRequestIdAndIsValidJson(): void
    {
        $response = $this->getJson('/api/ping');
        $response->assertStatus(200);

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertNotNull($requestId);

        Log::channel('payouts')->info('test.log', [
            'payout_id' => 'test-payout-uuid',
            'attempt' => 1,
        ]);

        $records = $this->testHandler->getRecords();
        $this->assertNotEmpty($records);

        $testRecord = null;
        foreach ($records as $record) {
            if ($record->message === 'test.log') {
                $testRecord = $record;
                break;
            }
        }

        $this->assertNotNull($testRecord, 'Expected test.log record not found');

        $formatter = new PayoutsJsonFormatter();
        $formatted = $formatter->format($testRecord);

        $decoded = json_decode($formatted, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('request_id', $decoded);
        $this->assertArrayHasKey('payout_id', $decoded);

        $this->assertSame('test.log', $decoded['message']);
        $this->assertSame($requestId, $decoded['request_id']);
        $this->assertSame('test-payout-uuid', $decoded['payout_id']);
    }
}
