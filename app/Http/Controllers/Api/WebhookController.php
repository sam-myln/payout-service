<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Idempotency\WebhookInboxContract;
use App\Jobs\ProcessWebhookEventJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Processing\Contracts\ProcessorRegistryContract;

final class WebhookController extends Controller
{
    public function handle(
        string $provider,
        Request $request,
        ProcessorRegistryContract $registry,
        WebhookInboxContract $inbox
    ): JsonResponse {
        if (!$registry->isEnabled($provider)) {
            abort(404);
        }

        $rawBody = $request->getContent();
        $headers = $this->headerMap($request);
        $signature = $headers['x-provider-signature'] ?? '';

        $notificationProcessor = $registry->factoryFor($provider)->makeNotification();

        $event = $notificationProcessor->verifyAndDecode($rawBody, $headers);

        $inserted = $inbox->recordOrIgnore($event->eventId, $event->providerPayoutId, $rawBody, $signature);

        if (!$inserted) {
            Log::info('webhook.duplicate', [
                'event_id' => $event->eventId,
                'provider' => $provider,
            ]);

            return response()->json(['status' => 'ok']);
        }

        ProcessWebhookEventJob::dispatch($provider, $event)->onQueue('webhooks');

        return response()->json(['status' => 'ok']);
    }

    /** @return array<string, string> */
    private function headerMap(Request $request): array
    {
        $map = [];
        foreach ($request->headers->all() as $name => $values) {
            $map[strtolower($name)] = (string) ($values[0] ?? '');
        }

        return $map;
    }
}
