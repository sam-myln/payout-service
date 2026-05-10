<?php declare(strict_types=1);

namespace App\Providers;

use App\Support\Metrics\RedisCounter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Processors\PaymentProviderDummy\Factory;

use function base_path;
use function config;

final class PaymentProviderDummyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/providers/'.Factory::SLUG.'.php'),
            'providers.'.Factory::SLUG
        );

        $this->app->singleton(Factory::class, static function (Application $app) {
            return new Factory(
                (array) config('providers.'.Factory::SLUG, []),
                $app->make(RedisCounter::class)
            );
        });
    }
}
