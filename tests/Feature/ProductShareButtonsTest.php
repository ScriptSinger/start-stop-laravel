<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShareButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_has_accessible_share_buttons(): void
    {
        $product = Product::query()->create(['name' => 'AKOM 60 Ah', 'slug' => 'akom-60-ah', 'price' => 8300, 'status' => true]);

        $html = $this->get(route('product.show', $product))->assertOk()->getContent();

        foreach (['vkontakte' => 'Поделиться во ВКонтакте', 'telegram' => 'Поделиться в Telegram', 'whatsapp' => 'Поделиться в WhatsApp', 'copy' => 'Копировать ссылку', 'native' => 'Поделиться'] as $network => $label) {
            $this->assertMatchesRegularExpression(
                '~<button type="button"[^>]*data-social="'.$network.'"[^>]*aria-label="'.$label.'"~u',
                $html,
                "Кнопка «{$network}» должна быть настоящей кнопкой с aria-label."
            );
        }

        $this->assertDoesNotMatchRegularExpression('~<div[^>]*data-social=~', $html, 'Кнопки шаринга не должны быть div: их не достать клавиатурой.');
    }
}
