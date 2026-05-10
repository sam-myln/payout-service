<?php declare(strict_types=1);

namespace Processing\Contracts;

interface PaymentCommandContract
{
    public function getUserId(): int;

    public function getAmount(): string;

    public function getCurrency(): string;

    public function getWallet(): string;

    public function getExternalReference(): string;
}
