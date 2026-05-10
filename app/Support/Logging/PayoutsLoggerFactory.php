<?php declare(strict_types=1);

namespace App\Support\Logging;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;

final class PayoutsLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('payouts');

        $levelName = ucfirst(strtolower($config['level'] ?? 'debug'));
        $level = Level::fromName($levelName);

        $maxFiles = (int) ($config['days'] ?? 14);

        $handler = new RotatingFileHandler(storage_path('logs/payouts.log'), $maxFiles, $level);
        $handler->setFormatter(new PayoutsJsonFormatter());
        $logger->pushHandler($handler);

        $logger->pushProcessor(new RequestIdProcessor());
        $logger->pushProcessor(new PayoutContextProcessor());

        return $logger;
    }
}
