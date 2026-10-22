<?php

namespace Tests\Feature;

use Database\Seeders\StorefrontSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Peer\CMS\Models\Category;
use Peer\CMS\Models\Form;
use Peer\CMS\Models\Menu;
use Peer\CMS\Models\MenuItem;
use Peer\CMS\Models\SettingField;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;
use Tests\TestCase;

/**
 * The storefront converted from Ravi Exclusive's old Blade storefront: every URL the old site
 * answered, and the pages its old admin wrote, served by the CMS from StorefrontSeeder.
 */
class StorefrontPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * URL => [the page's <title> as served, the components it places besides the layout's].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const PAGES = [
        '/' => ['Ravi Exclusive — Shop Ethnic &amp; Designer Wear Online | Amritsar', ['rc-category-showcase', 'rc-product-grid', 'rc-instagram-reels']],
        '/girls' => ['Girls Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', ['rc-product-listing']],
        '/boys' => ['Boys Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', ['rc-product-listing']],
        '/men' => ['Men Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', ['rc-product-listing']],
        '/women' => ['Women Ethnic &amp; Designer Wear | Ravi Exclusive Amritsar', ['rc-product-listing']],
        '/new-arrivals' => ['New Arrivals — Ravi Exclusive | Amritsar', ['rc-product-listing']],
        '/search?q=lehenga' => ['Search — Ravi Exclusive', ['rc-product-listing']],
        '/wishlist' => ['Your Wishlist — Ravi Exclusive', ['rc-product-listing']],
        '/product/rani-pink-kids-lehenga' => ['Shop Ethnic &amp; Designer Wear — Ravi Exclusive | Amritsar', ['rc-product-detail']],
        '/about-us' => ['About Ravi Exclusive | Ethnic Wear Store in Katra Jaimal Singh, Amritsar', []],
        '/contact-us' => ['Contact Ravi Exclusive | Katra Jaimal Singh, Amritsar', ['rc-contact-form']],
        '/shipping-policy' => ['Shipping Policy | Ravi Exclusive', []],
        '/returns-and-exchanges' => ['Returns &amp; Exchanges | Ravi Exclusive', []],
        '/privacy-policy' => ['Privacy Policy | Ravi Exclusive', []],
        '/terms-and-conditions' => ['Terms &amp; Conditions | Ravi Exclusive', []],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));
        $this->seed(StorefrontSeeder::class);
    }

    public function test_every_old_url_answers_with_its_title_its_components_and_no_blade(): void
    {
        foreach (self::PAGES as $url => [$title, $components]) {
            $response = $this->get($url)->assertOk();
            $html = (string) $response->getContent();

            $this->assertStringContainsString('<title>'.$title.'</title>', $html, $url);
            foreach (['rc-site-header', 'rc-cart-drawer', 'rc-site-footer', ...$components] as $component) {
                $this->assertStringContainsString($component, $html, "{$url} has no {$component}");
            }

            $this->assertDoesNotMatchRegularExpression('/@(if|else|foreach|include|extends|section|yield|php|csrf)\b|\{\{|\{!!|<x-|Unknown component/', $html, $url);
        }
    }

    public function test_no_page_writes_the_stores_details_into_its_markup(): void
    {
        foreach (array_keys(self::PAGES) as $url) {
            $html = (string) $this->get($url)->getContent();

            foreach (['8148146262', '814-814-62-62', '143001', 'ravi.exclusif', 'Jaimal+Singh'] as $detail) {
                $this->assertStringNotContainsString($detail, $html, "{$url} shows {$detail} without the settings");
            }
        }
    }

    public function test_the_stores_details_are_custom_settings_the_pages_bind_to(): void
    {
        $this->getJson('/api/cms/settings/store')
            ->assertOk()
            ->assertJsonPath('data.phone', '+918148146262')
            ->assertJsonPath('data.phone_display', '+91-814-814-62-62')
            ->assertJsonPath('data.whatsapp', '918148146262')
            ->assertJsonPath('data.business_name', 'M/s Ravi Exclusive')
            ->assertJsonPath('data.address_line1', '22, Katra Jaimal Singh')
            ->assertJsonPath('data.address_line2', 'Amritsar, Punjab, India – 143001')
            ->assertJsonPath('data.instagram_url', 'https://www.instagram.com/ravi.exclusif/')
            ->assertJsonPath('data.footer_about', 'Designer ethnic and occasion wear for the whole family, from the heart of Amritsar.');

        $this->get('/')->assertSee('data-setting="address_line1"', false)->assertSee('data-setting-href="tel:{phone}"', false);
    }

    public function test_the_menus_keep_the_old_links_labels_order_and_nesting(): void
    {
        $this->getJson('/api/cms/menus/header')
            ->assertOk()
            ->assertJsonPath('data.items.*.label', ['Girls', 'Boys', 'Men', 'Women', 'New Arrivals'])
            ->assertJsonPath('data.items.0.url', '/girls')
            ->assertJsonPath('data.items.0.children.*.label', ['Lehenga', 'Sharara / Palazzo', 'Patiala Salwar Suits / Farshi Salwar', 'Frocks & Gowns'])
            ->assertJsonPath('data.items.0.children.2.url', '/girls#patiala-farshi-salwar')
            ->assertJsonCount(6, 'data.items.1.children')
            ->assertJsonPath('data.items.2.children.1.url', '/men#waistcoat-sets')
            ->assertJsonPath('data.items.4.url', '/new-arrivals');

        $this->getJson('/api/cms/menus/footer-shop')->assertJsonPath('data.items.*.label', ['Boys', 'Girls', 'Women', 'Men']);
        $this->getJson('/api/cms/menus/footer-help')
            ->assertJsonPath('data.items.*.url', ['/#store', 'https://wa.me/{whatsapp}', '{instagram_url}'])
            ->assertJsonPath('data.items.1.target', '_blank');
        $this->getJson('/api/cms/menus/footer-policies')->assertJsonCount(6, 'data.items')->assertJsonPath('data.items.0.url', '/about-us');
    }

    public function test_the_store_follows_the_old_store_and_lists_its_categories_in_its_order(): void
    {
        $this->getJson('/api/store')
            ->assertJsonPath('data.name', 'Ravi Exclusive')
            ->assertJsonPath('data.orders', true)
            ->assertJsonPath('data.enquiry', false)
            ->assertJsonPath('data.pricing', true)
            ->assertJsonPath('data.stock', true);

        $this->getJson('/api/categories')
            ->assertJsonPath('data.*.slug', ['girls', 'boys', 'men', 'women'])
            ->assertJsonPath('data.0.children.2.name', 'Patiala Salwar Suits / Farshi Salwar');
    }

    public function test_a_product_page_answers_for_its_slug_and_its_id(): void
    {
        $product = app(ProductCatalogService::class)->save(new Product, [
            'name' => 'Rani Pink Kids Lehenga', 'slug' => '', 'sku' => 'RE-00005', 'price' => 3210, 'stock' => 4, 'is_active' => true,
            'category_ids' => [Category::where('name', 'Lehenga')->value('id')],
        ]);

        $this->get('/product/rani-pink-kids-lehenga')->assertOk()->assertSee('window.CMS_PARAMS={"slug":"rani-pink-kids-lehenga"}', false);
        $this->get('/product/'.$product->id)->assertOk()->assertSee('rc-product-detail', false);
        $this->getJson('/api/products/rani-pink-kids-lehenga')->assertJsonPath('data.categories.0.slug', 'lehenga');
    }

    public function test_the_seeder_runs_again_without_duplicating_or_undoing_the_owners_changes(): void
    {
        SettingField::where('key', 'phone_display')->update(['value' => '+91-98765-00000']);
        MenuItem::where('label', 'New Arrivals')->update(['label' => 'Just In']);

        $this->seed(StorefrontSeeder::class);

        $this->assertSame(1, Menu::where('slug', 'header')->count());
        $this->assertSame(1, Form::where('name', 'contact')->count());
        $this->assertSame(18, Category::count());
        $this->getJson('/api/cms/settings/store')->assertJsonPath('data.phone_display', '+91-98765-00000');
        $this->getJson('/api/cms/menus/header')->assertJsonCount(5, 'data.items')->assertJsonPath('data.items.4.label', 'Just In');
    }

    public function test_the_contact_page_posts_to_the_contact_form(): void
    {
        $message = ['contact_name' => 'Asha', 'contact_phone' => '', 'contact_email' => '', 'contact_location' => '', 'contact_message' => 'Do you have size 30?'];

        $this->postJson('/api/cms/form-submissions', $message)->assertUnprocessable()->assertJsonValidationErrors(['contact_phone']);
        $this->postJson('/api/cms/form-submissions', ['contact_email' => 'not-an-email'] + $message)->assertJsonValidationErrors(['contact_email']);
        $this->postJson('/api/cms/form-submissions', ['contact_phone' => '9999999999'] + $message)->assertCreated();
    }

    public function test_the_browser_keeps_only_the_cart_token_and_the_wishlist(): void
    {
        $sources = collect(File::allFiles(database_path('seeders/storefront')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['html', 'js'], true))
            ->mapWithKeys(fn ($file) => [$file->getRelativePathname() => $file->getContents()]);

        foreach ($sources as $path => $source) {
            $this->assertDoesNotMatchRegularExpression('/sessionStorage|indexedDB|serviceWorker|caches\./', $source, $path);
            $this->assertSame($path === 'layout.html' ? 1 : 0, substr_count($source, 'localStorage'), $path);
        }

        preg_match_all('/local\.(?:get|set)Item\(([^,)]+)/', $sources['layout.html'], $keys);
        $this->assertSame(["'cart_token'", "'cart_token'", 'WISHLIST_KEY', 'WISHLIST_KEY'], $keys[1]);
        $this->assertStringContainsString("cache: 'no-store'", $sources['layout.html']);
    }
}
