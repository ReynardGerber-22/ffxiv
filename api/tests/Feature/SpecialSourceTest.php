<?php

namespace Tests\Feature;

use App\Services\SpecialSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpecialSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_treasure_and_voyage_sources_are_cached_and_gil_alternatives_are_retained(): void
    {
        Http::preventStrayRequests();
        $trade = fn ($id, $currency) => ['tradeShops' => [['listings' => [
            ['item' => [['id' => (string) $id]], 'currency' => [['id' => (string) $currency]]],
        ]]]];
        $metadata = [
            14268 => $trade(14268, 28),
            14271 => ['treasure' => [6688]],
            14272 => ['voyages' => [['id' => 23, 'type' => 0]]],
            100 => ['treasure' => [6688], 'vendors' => [1001]],
            101 => $trade(101, 1),
            102 => [],
        ];
        $responses = [];
        foreach ($metadata as $id => $data) {
            $responses[SpecialSourceService::BASE_URL.$id.'.json'] = Http::response([
                'item' => ['id' => $id, 'name' => 'Item '.$id] + $data,
            ]);
        }
        Http::fake($responses);
        $service = app(SpecialSourceService::class);
        foreach ([1, 2] as $_) {
            $this->assertSame([14268, 14271, 14272], $service->blockedItemIds(array_keys($metadata)));
        }
        Http::assertSentCount(6);
    }

    public function test_invalid_metadata_is_not_cached_as_an_ordinary_source(): void
    {
        Http::fake([SpecialSourceService::BASE_URL.'14268.json' => Http::response(['item' => ['id' => 2]])]);
        try {
            app(SpecialSourceService::class)->blockedItemIds([14268]);
            $this->fail('Invalid source data must fail the calculation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Invalid material source', $exception->getMessage());
        }
        $this->assertNull(Cache::get('special-source:v1:14268'));
    }
}
