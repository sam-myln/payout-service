<?php declare(strict_types=1);

use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Exceptions\PayoutNotFoundException;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/ping', static fn() => response()->json(['pong' => true, 'ts' => now()->toIso8601String()]));

Route::post('/payouts', [PayoutController::class, 'store']);
Route::get('/payouts/{uuid}', [PayoutController::class, 'show']);
Route::post('/webhooks/{provider}', [WebhookController::class, 'handle']);

Route::get('/test/idempotency-conflict', static fn() => throw new IdempotencyConflictException());
Route::get('/test/invalid-signature', static fn() => throw new InvalidWebhookSignatureException());
Route::get('/test/payout-not-found', static fn() => throw PayoutNotFoundException::forUuid('00000000-0000-0000-0000-000000000000'));
Route::get('/test/validation-error', static fn() => throw ValidationException::withMessages([
    'amount' => ['The amount field is required.'],
    'wallet' => ['The wallet field is required.'],
]));
