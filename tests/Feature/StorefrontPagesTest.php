<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StorefrontCatalogSeeder::class);
    }

    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function storefrontPages(): array
    {
        return [
            'home' => ['shop.home', 'Ravi Exclusive — Shop Ethnic &amp; Designer Wear Online | Amritsar', null],
            'men' => ['shop.men', 'Men Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', 'Men'],
            'women' => ['shop.women', 'Women Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', 'Women'],
            'boys' => ['shop.boys', 'Boys Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', 'Boys'],
            'girls' => ['shop.girls', 'Girls Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', 'Girls'],
            'new arrivals' => ['shop.new-arrivals', 'New Arrivals — Ravi Exclusive | Amritsar', 'New Arrivals'],
            'search' => ['shop.search', 'Search — Ravi Exclusive', null],
            'wishlist' => ['shop.wishlist', 'Your Wishlist — Ravi Exclusive', null],
        ];
    }

    #[DataProvider('storefrontPages')]
    public function test_storefront_page_renders_with_shared_layout(string $routeName, string $title, ?string $activeNavLabel): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertSee("<title>{$title}</title>", false);
        $response->assertSee(asset('assets/css/style.css'), false);
        $response->assertSee('id="cartDrawer"', false);
        $response->assertDontSee('id="productModal"', false);
        $response->assertSee('id="toTop"', false);
        $response->assertDontSee('.html"', false);

        if ($routeName === 'shop.home' || $routeName === 'shop.wishlist') {
            $response->assertDontSee('id="filters"', false);
        } else {
            $response->assertSee('id="filters"', false);
            $response->assertSee('id="pager"', false);
            $response->assertSee('<nav class="chips"', false);
            $response->assertSee('<a class="chip on" href="#all" data-sub-link="">All <span class="chip-count" data-chip-count></span></a>', false);
            $response->assertDontSee('class="grp"', false);
        }

        if ($activeNavLabel !== null) {
            $response->assertSee('<a href="'.route($routeName).'" class="active">'.$activeNavLabel.'</a>', false);
        } else {
            $response->assertDontSee('class="active"', false);
        }
    }

    public function test_filter_panel_is_prefilled_from_the_query_string(): void
    {
        $response = $this->get(route('shop.men', [
            'q' => 'silk',
            'min' => 2000,
            'max' => 9000,
            'in_stock' => 1,
            'sort' => 'low',
            'per_page' => 24,
        ]));

        $response->assertOk();
        $response->assertSee('name="q" type="search" value="silk"', false);
        $response->assertSee('name="min" type="number" min="0" step="1" inputmode="numeric" value="2000"', false);
        $response->assertSee('name="max" type="number" min="0" step="1" inputmode="numeric" value="9000"', false);
        $response->assertSee('name="in_stock" type="checkbox" value="1" checked', false);
        $response->assertSee('<option value="low" selected>', false);
        $response->assertSee('<option value="24" selected>', false);
    }

    public function test_filter_panel_escapes_the_search_term(): void
    {
        $response = $this->get(route('shop.search', ['q' => '"><script>alert(1)</script>']));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false);
    }

    public function test_category_page_lists_its_products_from_the_database(): void
    {
        $boys = Category::firstWhere('slug', 'boys');
        $boysProductCount = Product::whereIn('category_id', $boys->children()->pluck('id'))->count();

        $response = $this->get(route('shop.boys'));

        $response->assertOk();
        $this->assertSame(22, $boysProductCount);
        $this->assertSame($boysProductCount, substr_count($response->getContent(), '<article class="pcard"'));
        $response->assertSee('<span class="count" id="count">22 items</span>', false);
        $response->assertSee('Ivory Embroidered Kurta Set');
        $response->assertDontSee('Festive Sherwani');
        $response->assertSee('<div class="empty" id="emptyMsg"  hidden >', false);
    }

    public function test_category_tabs_come_from_its_subcategories(): void
    {
        $response = $this->get(route('shop.girls'));

        $response->assertSeeInOrder(['>Lehenga <', '>Sharara / Palazzo <', '>Patiala Salwar Suits / Farshi Salwar <', '>Frocks &amp; Gowns <'], false);
        $response->assertSee('<a class="chip" href="#lehenga" data-sub-link="lehenga" data-sub-name="Lehenga">', false);
    }

    public function test_product_card_carries_what_the_storefront_script_reads(): void
    {
        $sherwani = Product::firstWhere('name', 'Festive Sherwani');

        $response = $this->get(route('shop.men'));

        $response->assertSee('data-id="'.$sherwani->id.'" data-code="RE-0035" data-name="Festive Sherwani" data-cat="men" data-cat-name="Men"', false);
        $response->assertSee('data-sub="Indo-Westerns" data-sub-slug="indo-westerns" data-price="5399" data-mrp="7199" data-new="0"', false);
        $response->assertSee('data-stock="1" data-fabric="Silk Blend"', false);
        $response->assertSee('data-sizes="S|M|L|XL|XXL|3XL" data-search="festive sherwani indo-westerns men silk blend"', false);
        $response->assertSee('<img class="ph" src="/assets/images/categories/men-indo-westerns.webp" alt="Festive Sherwani"', false);
        $response->assertSee('<span class="ptag off">25% off</span>', false);
        $response->assertSee('<div class="price"><b>₹5,399</b><s>₹7,199</s><em>25% off</em></div>', false);
    }

    public function test_sold_out_and_inactive_products(): void
    {
        $soldOut = Product::firstWhere('name', 'Festive Sherwani');
        $soldOut->variants()->update(['stock' => 0]);
        Product::firstWhere('name', 'Pure Silk Sherwani')->update(['is_active' => false]);

        $response = $this->get(route('shop.men'));

        $response->assertSee('data-id="'.$soldOut->id.'"', false);
        $response->assertSee('data-stock="0"', false);
        $response->assertSee('<span class="ptag off">Sold out</span>', false);
        $response->assertDontSee('Pure Silk Sherwani');
    }

    public function test_new_arrivals_lists_only_recent_products(): void
    {
        $recent = Product::where('created_at', '>=', now()->subDays(30))->count();

        $response = $this->get(route('shop.new-arrivals'));

        $this->assertGreaterThan(0, $recent);
        $this->assertLessThan(Product::count(), $recent);
        $this->assertSame($recent, substr_count($response->getContent(), '<article class="pcard"'));
        $response->assertDontSee('Festive Sherwani');
    }

    public function test_home_shows_four_new_arrivals_and_the_bestsellers(): void
    {
        $response = $this->get(route('shop.home'));

        $this->assertSame(8, substr_count($response->getContent(), '<article class="pcard"'));
        foreach (Product::featured()->pluck('name') as $bestseller) {
            $response->assertSee($bestseller);
        }
    }

    public function test_wishlist_renders_every_product_hidden_until_the_script_picks_saved_ones(): void
    {
        $response = $this->get(route('shop.wishlist'));

        $this->assertSame(Product::count(), substr_count($response->getContent(), '<div class="col d-none"'));
    }
}
