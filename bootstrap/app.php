<?php

use App\Domain\Exceptions\IdempotencyConflictException;
use App\Domain\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Exceptions\PayoutNotFoundException;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Support\Http\ErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            RequestIdMiddleware::class,
            IdempotencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (IdempotencyConflictException $e, $request) {
            return response()->json(
                ErrorResponse::idempotencyConflict($request->header('X-Request-Id', (string) Str::uuid()))->toArray(),
                409,
            );
        });

        $exceptions->render(function (InvalidWebhookSignatureException $e, $request) {
            return response()->json(
                ErrorResponse::invalidSignature($request->header('X-Request-Id', (string) Str::uuid()))->toArray(),
                401,
            );
        });

        $exceptions->render(function (PayoutNotFoundException $e, $request) {
            return response()->json(
                ErrorResponse::notFound($request->header('X-Request-Id', (string) Str::uuid()))->toArray(),
                404,
            );
        });

        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json(
                ErrorResponse::validation(
                    $e->errors(),
                    $request->header('X-Request-Id', (string) Str::uuid()),
                )->toArray(),
                422,
            );
        });

        $exceptions->render(function (Throwable $e, $request) {
            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            $message = app()->environment('production')
                ? 'Internal Server Error'
                : 'An unexpected error occurred';

            return response()->json(
                (new ErrorResponse(
                    'internal_error',
                    $message,
                    $request->header('X-Request-Id', (string) Str::uuid()),
                ))->toArray(),
                $status,
            );
        });
    })->create();
