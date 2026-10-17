<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StorefrontCatalogSeeder::class);
    }

    public function test_it_shows_the_product_with_its_order_controls(): void
    {
        $product = $this->product('Festive Co-ord Set');

        $response = $this->get(route('shop.product', $product->slug));

        $response->assertOk();
        $response->assertSee('<title>'.e($product->meta_title).'</title>', false);
        $response->assertSee('<h1>Festive Co-ord Set</h1>', false);
        $response->assertSee('data-price="19999"', false);
        $response->assertSee('data-url="'.route('shop.product', $product->slug).'"', false);
        $response->assertSeeInOrder(['data-size="XS"', 'data-size="S"', 'data-size="XXL"'], false);
        $response->assertSee('id="pdBuy"', false);
        $response->assertSee('RE-0031');
        $response->assertSee('Matching ethnic co-ord sets for an easy, put-together festive look.');
        $response->assertSeeInOrder(['Home', 'Women', 'Co-ord Sets', 'Festive Co-ord Set']);
    }

    public function test_the_link_preview_shows_the_product_photo(): void
    {
        $product = $this->product('Festive Co-ord Set');
        $url = route('shop.product', $product->slug);

        $response = $this->get($url);

        $response->assertSee('<link rel="canonical" href="'.$url.'">', false);
        $response->assertSee('<meta property="og:url" content="'.$url.'">', false);
        $response->assertSee('<meta property="og:image" content="'.url('/assets/images/categories/women-co-ord-sets.webp').'">', false);
        $response->assertSee('<meta property="og:title" content="Festive Co-ord Set', false);
    }

    public function test_it_suggests_other_products_from_the_same_subcategory(): void
    {
        $product = $this->product('Festive Co-ord Set');

        $response = $this->get(route('shop.product', $product->slug));

        $response->assertSee('You may also like');
        $response->assertSee('Chikankari Co-ord Set');
        $response->assertSee('Royal Co-ord Set');
        $response->assertDontSee('<article class="pcard" data-id="'.$product->id.'"', false);
    }

    public function test_it_suggests_at_most_four_products(): void
    {
        $product = $this->product('Classic Kurta Pyjama');

        $html = $this->get(route('shop.product', $product->slug))->getContent();

        $this->assertSame(4, substr_count($html, '<article class="pcard"'));
    }

    public function test_an_inactive_product_is_not_found(): void
    {
        $product = $this->product('Festive Co-ord Set');
        $product->update(['is_active' => false]);

        $this->get(route('shop.product', $product->slug))->assertNotFound();
    }

    public function test_a_product_in_an_inactive_category_is_not_found(): void
    {
        $product = $this->product('Festive Co-ord Set');
        Category::firstWhere('slug', 'women-co-ord-sets')->update(['is_active' => false]);

        $this->get(route('shop.product', $product->slug))->assertNotFound();
    }

    public function test_an_unknown_product_is_not_found(): void
    {
        $this->get(route('shop.product', 'no-such-product'))->assertNotFound();
    }

    public function test_a_product_without_active_sizes_is_sold_as_free_size(): void
    {
        $product = $this->product('Festive Co-ord Set');
        $product->variants()->update(['is_active' => false]);

        $this->get(route('shop.product', $product->slug))
            ->assertSee('<button type="button" class="on" data-size="Free Size">Free Size</button>', false);
        $this->get(route('shop.women'))
            ->assertSee('data-id="'.$product->id.'" data-code="RE-0031"', false)
            ->assertSee('data-sizes="Free Size"', false);
    }

    public function test_product_cards_link_to_their_product_page(): void
    {
        $product = $this->product('Festive Co-ord Set');
        $url = route('shop.product', $product->slug);

        $response = $this->get(route('shop.women'));

        $response->assertSee('data-url="'.$url.'"', false);
        $response->assertSee('<h4><a href="'.$url.'">Festive Co-ord Set</a></h4>', false);
        $response->assertSee('<a class="pimg-link" href="'.$url.'" tabindex="-1" aria-hidden="true"></a>', false);
        $response->assertDontSee('data-action="quick-view"', false);
        $response->assertDontSee('Quick view');
    }

    private function product(string $name): Product
    {
        return Product::query()->where('name', $name)->firstOrFail();
    }
}
