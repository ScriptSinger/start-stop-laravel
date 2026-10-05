<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_add_returns_count_for_header_and_lists_products(): void
    {
        $product = $this->product('TITAN 60');

        $this->postJson(route('wishlist.store', $product))
            ->assertOk()
            ->assertJson(['count' => 1, 'message' => '«TITAN 60» добавлен в закладки.']);

        // Повторное добавление не дублирует.
        $this->postJson(route('wishlist.store', $product))->assertJson(['count' => 1]);

        $this->get(route('wishlist.index'))->assertOk()->assertSee('TITAN 60');
        $this->get(route('home'))->assertSeeInOrder(['header-wishlist__total-items', '>1<'], false);

        $this->deleteJson(route('wishlist.destroy', $product))->assertJson(['count' => 0]);
        $this->get(route('wishlist.index'))->assertSee('Ваши закладки пусты.');
    }

    public function test_compare_keeps_four_latest_products(): void
    {
        $products = collect(range(1, 5))->map(fn (int $i) => $this->product("АКБ {$i}"));

        foreach ($products as $product) {
            $this->postJson(route('compare.store', $product));
        }

        $this->get(route('compare.index'))
            ->assertOk()
            ->assertDontSee('АКБ 1<', false)
            ->assertSee('АКБ 2')
            ->assertSee('АКБ 5');

        $this->postJson(route('compare.store', $products->last()))->assertJson(['count' => 4]);
    }

    public function test_compare_table_lists_specifications_of_all_products(): void
    {
        $polarity = Attribute::query()->create(['name' => 'Полярность', 'display_sort_order' => 2]);
        $voltage = Attribute::query()->create(['name' => 'Напряжение', 'display_sort_order' => 1]);
        $first = $this->product('Первый');
        $second = $this->product('Второй');
        $first->attributeValues()->attach($polarity->values()->create(['value' => 'Обратная']));
        $second->attributeValues()->attach($voltage->values()->create(['value' => '12V']));

        $this->post(route('compare.store', $first));
        $this->post(route('compare.store', $second));

        $this->get(route('compare.index'))
            ->assertSee('Показывать только отличия')
            ->assertSeeInOrder(['Напряжение', 'Полярность'])
            ->assertSee('Обратная')
            ->assertSee('12V');
    }

    public function test_works_without_javascript_and_shows_notice(): void
    {
        $product = $this->product('TITAN 60');

        $this->from(route('product.show', $product))
            ->post(route('compare.store', $product))
            ->assertRedirect(route('product.show', $product))
            ->assertSessionHas('notice', '«TITAN 60» добавлен в сравнение.');

        $this->withSession(['notice' => 'Готово'])->get(route('home'))->assertSee('data-notice="Готово"', false);
    }

    public function test_inactive_products_drop_out_and_cannot_be_added(): void
    {
        $product = $this->product('TITAN 60');
        $this->post(route('wishlist.store', $product));
        $product->update(['status' => false]);

        $this->get(route('wishlist.index'))->assertSee('Ваши закладки пусты.');
        $this->post(route('compare.store', $product))->assertNotFound();
    }

    private function product(string $name): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value().'-'.uniqid(),
            'price' => 7000,
            'quantity' => 1,
            'status' => true,
        ]);
    }
}
