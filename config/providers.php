<?php declare(strict_types=1);

use Processors\PaymentProviderDummy\Factory as DummyFactory;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled providers
    |--------------------------------------------------------------------------
    |
    | Slugs of payment providers wired into the application. The PayoutController
    | only accepts requests targeting a slug listed here, and the WebhookController
    | only routes /webhooks/{provider} for slugs listed here.
    |
    */
    'enabled' => array_filter(explode(',', (string) env('PROVIDERS_ENABLED', 'dummy'))),

    /*
    |--------------------------------------------------------------------------
    | Factory map
    |--------------------------------------------------------------------------
    |
    | Slug => Factory class. The Factory is responsible for constructing the
    | provider's PaymentProcessor and NotificationProcessor from per-provider
    | configuration.
    |
    | Per-provider configuration lives under `providers.<slug>` and is loaded
    | by each provider's dedicated ServiceProvider (see
    | bootstrap/providers.php), not from this file.
    |
    */
    'factories' => [
        DummyFactory::SLUG => DummyFactory::class,
    ],

];
