<?php declare(strict_types=1);

namespace Processors\PaymentProviderDummy;

use App\Support\Metrics\RedisCounter;
use Processing\Contracts\NotificationProcessorContract;
use Processing\Contracts\PaymentProcessorContract;
use Processing\Contracts\ProcessorFactoryContract;

final class Factory implements ProcessorFactoryContract
{
    public const SLUG = 'dummy';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config, private readonly RedisCounter $counter)
    {
    }

    public function makePayment(): PaymentProcessorContract
    {
        return new PaymentProcessor(
            $this->config['base_url'],
            $this->config['timeout_connect'],
            $this->config['timeout_read'],
            $this->counter
        );
    }

    public function makeNotification(): NotificationProcessorContract
    {
        return new NotificationProcessor($this->config);
    }
}
