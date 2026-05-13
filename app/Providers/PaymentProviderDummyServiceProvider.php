<?php declare(strict_types=1);

namespace App\Providers;

use App\Support\Metrics\RedisCounter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Processing\Exceptions\ProviderConfigException;
use Processors\PaymentProviderDummy\Factory;

use function config;

final class PaymentProviderDummyServiceProvider extends ServiceProvider
{
    /**
     * @throws ProviderConfigException
     */
    public function register(): void
    {
        if (!config('providers.'.Factory::SLUG)) {
            throw new ProviderConfigException('Wrong provider configuration');
        }
        $this->app->singleton(Factory::class, static function (Application $app) {
            return new Factory(
                (array) config('providers.'.Factory::SLUG),
                $app->make(RedisCounter::class)
            );
        });
    }
}
