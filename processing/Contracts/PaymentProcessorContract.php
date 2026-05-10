<?php declare(strict_types=1);

namespace Processing\Contracts;

use Processing\OutboundPaymentCommand;
use Processing\PaymentResult;

interface PaymentProcessorContract
{
    public function send(OutboundPaymentCommand $command): PaymentResult;
}
