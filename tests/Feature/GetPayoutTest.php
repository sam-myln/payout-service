<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Payout\Payout;
use App\Domain\Payout\PayoutRepositoryContract;
use App\Domain\Payout\UuidGeneratorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Tests\TestCase;

final class GetPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function testExistingPayoutReturns200WithUuid(): void
    {
        $repo = $this->app->make(PayoutRepositoryContract::class);
        $uuidGen = $this->app->make(UuidGeneratorContract::class);

        $payout = Payout::create(
            'dummy',
            42,
            new Money(150000000, new Currency('USDT')),
            'TX123456',
            'ref-get-001',
            $uuidGen
        );
        $repo->save($payout);

        $response = $this->getJson("/api/payouts/{$payout->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.uuid', $payout->uuid);
        $response->assertJsonMissingPath('data.id');
    }

    public function testUnknownUuidReturns404(): void
    {
        $response = $this->getJson('/api/payouts/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function testRandomUuidReturns404(): void
    {
        $uuid = (string) Str::uuid();

        $response = $this->getJson("/api/payouts/{$uuid}");

        $response->assertStatus(404);
    }

    public function testNumericIdReturns404(): void
    {
        $response = $this->getJson('/api/payouts/123');

        $response->assertStatus(404);
    }

    public function testProductionEnvReturns404(): void
    {
        app()['env'] = 'production';

        $repo = $this->app->make(PayoutRepositoryContract::class);
        $uuidGen = $this->app->make(UuidGeneratorContract::class);

        $payout = Payout::create(
            'dummy',
            42,
            new Money(150000000, new Currency('USDT')),
            'TX123456',
            'ref-prod-001',
            $uuidGen
        );
        $repo->save($payout);

        $response = $this->getJson("/api/payouts/{$payout->uuid}");
        $response->assertStatus(404);

        app()['env'] = 'testing';
    }
}
