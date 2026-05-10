<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Payout\PayoutRepositoryContract;
use App\DTO\CreatePayoutCommand;
use App\DTO\PayoutData;
use App\Services\CreatePayoutHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PayoutController extends Controller
{
    public function store(CreatePayoutCommand $payoutCommand, CreatePayoutHandler $handler): JsonResponse
    {
        $payout = $handler->handle($payoutCommand);

        return response()->json(
            ['data' => PayoutData::fromDomain($payout)->toArray()],
            202
        );
    }

    public function show(string $uuid, PayoutRepositoryContract $repo): JsonResponse
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $payout = $repo->find($uuid);

        if ($payout === null) {
            abort(404);
        }

        return response()->json(
            ['data' => PayoutData::fromDomain($payout)->toArray()]
        );
    }
}
