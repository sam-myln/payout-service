<?php declare(strict_types=1);

namespace App\Providers;

use App\Domain\Idempotency\IdempotencyStoreContract;
use App\Domain\Idempotency\WebhookInboxContract;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\PayoutStateManager;
use App\Domain\Payout\PayoutStateManagerContract;
use App\Domain\Payout\UuidGeneratorContract;
use App\Domain\Retry\ExponentialBackoffRetryPolicy;
use App\Domain\Retry\RetryPolicyContract;
use App\Infrastructure\Idempotency\EloquentWebhookInbox;
use App\Infrastructure\Idempotency\RedisIdempotencyStore;
use App\Infrastructure\Persistence\PayoutRepository;
use App\Support\Uuid\LaravelUuidGenerator;
use Illuminate\Support\ServiceProvider;
use Money\Currencies;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\MoneyFormatter;
use Money\MoneyParser;
use Money\Parser\DecimalMoneyParser;
use Processing\ConfigDrivenProcessorRegistry;
use Processing\Contracts\ProcessorRegistryContract;
use Traversable;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UuidGeneratorContract::class, LaravelUuidGenerator::class);

        $this->app->singleton(Currencies::class, static function () {
            $custom = new class implements Currencies {
                public function contains(Currency $currency): bool
                {
                    return $currency->getCode() === 'USDT';
                }

                public function subunitFor(Currency $currency): int
                {
                    return $currency->getCode() === 'USDT' ? 6 : 0;
                }

                public function getIterator(): Traversable
                {
                    yield new Currency('USDT');
                }
            };

            return new AggregateCurrencies([new ISOCurrencies(), $custom]);
        });

        $this->app->singleton(MoneyFormatter::class, DecimalMoneyFormatter::class);
        $this->app->singleton(MoneyParser::class, DecimalMoneyParser::class);

        $this->app->bind(PayoutRepositoryContract::class, PayoutRepository::class);

        $this->app->bind(RetryPolicyContract::class, ExponentialBackoffRetryPolicy::class);

        $this->app->bind(IdempotencyStoreContract::class, RedisIdempotencyStore::class);

        $this->app->bind(WebhookInboxContract::class, EloquentWebhookInbox::class);

        $this->app->bind(PayoutStateManagerContract::class, PayoutStateManager::class);

        $this->app->singleton(ProcessorRegistryContract::class, static function ($app) {
            return new ConfigDrivenProcessorRegistry(
                (array) config('providers.enabled', []),
                (array) config('providers.factories', []),
                $app
            );
        });
    }

    public function boot(): void
    {
    }
}
