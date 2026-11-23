<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private const DADATA_URL = 'https://suggestions.dadata.ru/*';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.dadata.key' => 'test-key']);
    }

    public function test_endpoint_returns_suggestions_limited_to_ufa_and_sends_the_key_from_the_server(): void
    {
        Http::fake([self::DADATA_URL => Http::response(['suggestions' => [
            ['value' => 'г Уфа, ул Рихарда Зорге, д 63'],
            ['value' => 'г Уфа, ул Рихарда Зорге, д 6'],
        ]])]);

        $this->getJson(route('checkout.address-suggestions', ['query' => 'Рихарда Зорге 6']))
            ->assertOk()
            ->assertExactJson(['suggestions' => ['г Уфа, ул Рихарда Зорге, д 63', 'г Уфа, ул Рихарда Зорге, д 6']]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Token test-key')
            && $request['query'] === 'Рихарда Зорге 6'
            && $request['locations'] === [['kladr_id' => '0200000100000']]);
    }

    public function test_repeated_query_is_served_from_the_cache(): void
    {
        Http::fake([self::DADATA_URL => Http::response(['suggestions' => [['value' => 'г Уфа, ул Ленина']]])]);

        $this->getJson(route('checkout.address-suggestions', ['query' => 'Ленина']))->assertOk();
        $this->getJson(route('checkout.address-suggestions', ['query' => 'ленина']))
            ->assertOk()->assertJsonPath('suggestions.0', 'г Уфа, ул Ленина');

        Http::assertSentCount(1);
    }

    public function test_without_a_key_it_returns_nothing_and_does_not_call_dadata(): void
    {
        config(['services.dadata.key' => null]);
        Http::fake();

        $this->getJson(route('checkout.address-suggestions', ['query' => 'Ленина']))
            ->assertOk()->assertExactJson(['suggestions' => []]);

        Http::assertNothingSent();
    }

    public function test_dadata_failure_gives_an_empty_list_and_is_not_cached(): void
    {
        Http::fakeSequence(self::DADATA_URL)
            ->push(['message' => 'limit'], 429)
            ->pushFailedConnection()
            ->push(['suggestions' => [['value' => 'г Уфа, ул Ленина']]]);

        $url = route('checkout.address-suggestions', ['query' => 'Ленина']);

        $this->getJson($url)->assertOk()->assertExactJson(['suggestions' => []]);
        $this->getJson($url)->assertOk()->assertExactJson(['suggestions' => []]);
        $this->getJson($url)->assertOk()->assertJsonPath('suggestions.0', 'г Уфа, ул Ленина');
    }

    public function test_short_or_missing_query_is_rejected_before_calling_dadata(): void
    {
        Http::fake();

        $this->getJson(route('checkout.address-suggestions', ['query' => 'Уф']))->assertUnprocessable();
        $this->getJson(route('checkout.address-suggestions'))->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_cart_page_uses_suggestions_only_when_the_key_is_configured(): void
    {
        $product = Product::query()->create(['name' => 'TITAN 60Ah', 'slug' => 'titan-60', 'price' => 7000, 'quantity' => 3, 'status' => true]);

        $this->post(route('cart.store', $product), ['quantity' => 1])->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('addressSuggest', false)
            ->assertSee('checkout\\/address-suggestions', false);

        config(['services.dadata.key' => null]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertDontSee('addressSuggest', false)
            ->assertSee('autocomplete="street-address"', false);
    }
}
