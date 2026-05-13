<?php declare(strict_types=1);

return [
    'base_url' => 'https://provider.example',
    'webhook_secret' => 'secret123',
    'timeout_connect' => (float) env('PROVIDER_TIMEOUT_CONNECT', 2.0),
    'timeout_read' => (float) env('PROVIDER_TIMEOUT_READ', 5.0),
];
