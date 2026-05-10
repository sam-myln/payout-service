<?php declare(strict_types=1);

namespace Processing\Contracts;

interface ProcessorFactoryContract
{
    public function makePayment(): PaymentProcessorContract;

    public function makeNotification(): NotificationProcessorContract;
}
