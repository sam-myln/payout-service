<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy;

use App\Domain\Exceptions\ProviderContractViolationException;
use App\Domain\Exceptions\ProviderRateLimitedException;
use App\Domain\Exceptions\ProviderTimeoutException;
use App\Domain\Exceptions\ProviderUnavailableException;
use App\Domain\Exceptions\ProviderValidationException;
use App\Support\Metrics\RedisCounter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Processing\Contracts\PaymentProcessorContract;
use Processing\OutboundPaymentCommand;
use Processing\PaymentResult;
use Processors\PaymentProviderDummy\Api\Requests\PaymentRequest;
use Processors\PaymentProviderDummy\Api\Responses\PaymentResponse;

final class PaymentProcessor implements PaymentProcessorContract
{
    /** @param array{base_url: ?string} $config */
    public function __construct(private readonly array $config, private readonly RedisCounter $counter)
    {
    }

    public function send(OutboundPaymentCommand $command): PaymentResult
    {
        $request = PaymentRequest::fromCommand($command);

        $baseUrl = $this->config['base_url'];
        $connectTimeout = 2.0;
        $readTimeout = 5.0;

        try {
            $response = Http::baseUrl($baseUrl)
                ->timeout($readTimeout)
                ->connectTimeout($connectTimeout)
                ->post('/provider/payouts', $request->toArray());
        } catch (ConnectionException $e) {
            Log::warning('Provider connection timed out', [
                'base_url' => $baseUrl,
                'timeout' => $readTimeout,
            ]);

            throw new ProviderTimeoutException("Provider connection timed out after {$readTimeout}s", previous: $e);
        }

        return $this->mapResponse($response);
    }

    private function mapResponse($response): PaymentResult
    {
        $statusCode = $response->status();
        $rawBody = $response->body();

        if ($statusCode === 429) {
            $retryAfter = $this->parseRetryAfter($response);
            $this->counter->increment('provider.429');
            Log::warning('Provider rate limited', [
                'status_code' => $statusCode,
                'retry_after' => $retryAfter,
            ]);

            throw new ProviderRateLimitedException($retryAfter);
        }

        if ($statusCode >= 500) {
            $this->counter->increment('provider.5xx');
            Log::error('Provider unavailable', [
                'status_code' => $statusCode,
                'body_preview' => mb_substr($rawBody, 0, 200),
            ]);

            throw ProviderUnavailableException::fromStatusCode($statusCode, $rawBody);
        }

        if ($statusCode === 200 || $statusCode === 202) {
            try {
                $decoded = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);

                return PaymentResponse::validateAndCreate($decoded)->toResult();
            } catch (JsonException $e) {
                $this->counter->increment('provider.contract_violation');
                Log::error('Provider returned malformed JSON', [
                    'status_code' => $statusCode,
                    'body_preview' => mb_substr($rawBody, 0, 500),
                ]);

                throw ProviderContractViolationException::fromRawBody($rawBody, $e);
            } catch (ValidationException $e) {
                $this->counter->increment('provider.contract_violation');
                Log::error('Provider response shape violated contract', [
                    'status_code' => $statusCode,
                    'body_preview' => mb_substr($rawBody, 0, 500),
                    'validation_errors' => json_encode($e->errors()),
                ]);

                throw ProviderContractViolationException::fromRawBody($rawBody, $e);
            }
        }

        Log::error('Provider rejected request', [
            'status_code' => $statusCode,
            'body_preview' => mb_substr($rawBody, 0, 200),
        ]);

        throw ProviderValidationException::fromStatusCode($statusCode, $rawBody);
    }

    private function parseRetryAfter($response): ?int
    {
        $header = $response->header('Retry-After');

        if ($header === null) {
            return null;
        }

        if (is_numeric($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);
        if ($timestamp !== false) {
            return max(0, $timestamp - time());
        }

        Log::warning('Unparseable Retry-After header', ['header' => $header]);

        return null;
    }
}
