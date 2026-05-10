<?php declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Validation\ValidationException;
use Money\Currency;
use Money\Money;
use Processors\PaymentProviderDummy\Api\Requests\PaymentRequest as CreatePayoutCommand;
use Tests\TestCase;

final class SmokeDataCommandTest extends TestCase
{
    public function testValidDto(): void
    {
        $dto = CreatePayoutCommand::validateAndCreate([
            'userId' => 1,
            'amount' => '150.00',
            'currency' => 'USDT',
            'wallet' => '0xABC123DEF456',
            'externalReference' => 'ref-123',
        ]);

        $this->assertSame(1, $dto->userId);
        $this->assertSame('150.00', $dto->amount);
        $this->assertSame('USDT', $dto->currency);
    }

    public function testRoundTrip(): void
    {
        $validArray = [
            'userId' => 2,
            'amount' => '99.50',
            'currency' => 'EUR',
            'wallet' => 'iban-1234',
            'externalReference' => 'ref-roundtrip',
        ];

        $dto = CreatePayoutCommand::validateAndCreate($validArray);
        $back = $dto->toArray();

        $this->assertSame(2, $back['userId']);
        $this->assertSame('99.50', $back['amount']);
        $this->assertSame('EUR', $back['currency']);
        $this->assertSame('iban-1234', $back['wallet']);
        $this->assertSame('ref-roundtrip', $back['externalReference']);
    }

    public function testInvalidRejected(): void
    {
        $this->expectException(ValidationException::class);

        CreatePayoutCommand::validateAndCreate([
            'wallet' => '',
            'userId' => 1,
            'amount' => '150.00',
            'currency' => 'USDT',
            'externalReference' => 'ref-123',
        ]);
    }

    public function testFromDoesNotValidate(): void
    {
        $dto = CreatePayoutCommand::from([
            'userId' => 1,
            'amount' => '',
            'currency' => 'XXX',
            'wallet' => '',
            'externalReference' => 'ref',
        ]);

        $this->assertSame(1, $dto->userId);
        $this->assertSame('', $dto->wallet);
    }

    public function testUsdtMoneySixDecimals(): void
    {
        $money = new Money(150_000_000, new Currency('USDT'));

        $this->assertSame('150000000', $money->getAmount());
        $this->assertSame('USDT', $money->getCurrency()->getCode());
    }

    public function testUsdMoneyTwoDecimals(): void
    {
        $usd = new Money(15000, new Currency('USD'));

        $this->assertSame('15000', $usd->getAmount());
        $this->assertSame('USD', $usd->getCurrency()->getCode());
    }
}
