<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Idempotency\IdempotencyStoreContract;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    public function __construct(private readonly IdempotencyStoreContract $store)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is('api/payouts') || !$request->isMethod('POST')) {
            return $next($request);
        }

        $rawKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($rawKey) && $rawKey !== ''
            ? $rawKey
            : null;

        if ($idempotencyKey === null) {
            return $next($request);
        }

        $fingerprint = hash('sha256', $request->getContent());

        [$response] = $this->store->remember(
            $idempotencyKey,
            $fingerprint,
            static fn() => $next($request)
        );

        return $response;
    }
}
