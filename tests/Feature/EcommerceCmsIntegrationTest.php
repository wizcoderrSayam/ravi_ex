<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Layout;
use Peer\CMS\Models\Page;
use Peer\CMS\Support\GitCommit;
use Peer\Ecommerce\EcommerceServiceProvider;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;
use Peer\Ecommerce\Services\Catalog\ProductSpreadsheetService;
use Tests\TestCase;

class EcommerceCmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_are_sections_of_the_cms_settings_read_typed(): void
    {
        $this->assertSame(49, CMS::settings()->get('shipping.flat_rate'));
        $this->assertFalse(CMS::settings()->get('shipping.show_delivery_details'));

        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertOk()
            ->assertViewHas('config', fn (array $config): bool => collect($config['sections'])->pluck('key')->all() === [
                'general', 'forms', 'smtp', 'commerce', 'payments', 'paytm', 'notifications', 'shipping', 'shiprocket', 'features',
            ]);

        $this->putJson(route('cms.panel.settings.update'), [
            'shipping' => ['flat_rate' => '59.5', 'charges_enabled' => false],
        ])->assertOk();

        $this->assertSame(59.5, CMS::settings()->get('shipping.flat_rate'));
        $this->assertFalse(CMS::settings()->get('shipping.charges_enabled'));
    }

    public function test_the_cms_settings_show_the_commit_the_store_is_on(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertViewHas('config', fn (array $config): bool => array_keys($config['commits']) === ['CMS', 'App', 'Ecommerce']
                && $config['commits']['Ecommerce'] === GitCommit::of(base_path('packages/ecommerce')));
    }

    public function test_the_panel_header_shows_both_releases_and_the_editor_only_the_cms_release(): void
    {
        $page = Page::factory()->create(['slug' => 'about', 'status' => 'published', 'layout_id' => Layout::firstOrCreateStarter()->id]);
        $this->actingAs($this->admin());

        foreach ([route('admin.dashboard'), route('cms.panel.dashboard')] as $url) {
            $this->get($url)->assertOk()->assertSeeInOrder(['CMS '.CMS::version(), 'Ecommerce '.EcommerceServiceProvider::VERSION]);
        }

        $this->get(route('cms.editor.show', $page->slug))
            ->assertOk()
            ->assertSee('CMS '.CMS::version())
            ->assertDontSee('Ecommerce '.EcommerceServiceProvider::VERSION);
    }

    public function test_the_sidebar_dashboard_opens_the_store_dashboard_and_the_store_has_no_overview(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('admin.dashboard').'" title="Dashboard"', false)
            ->assertDontSee('href="'.route('cms.panel.dashboard').'" title="Dashboard"', false)
            ->assertDontSee('title="Overview"', false);
    }

    public function test_a_store_settings_action_answers_with_the_message_the_cms_shows(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.shiprocket-pickup-locations'))
            ->assertExactJson(['success' => true, 'message' => 'Pickup locations: Primary Warehouse (110001)']);
    }

    public function test_products_take_cms_categories_and_media_library_images(): void
    {
        $this->actingAs($this->admin());
        $spices = $this->postJson(route('cms.panel.categories.store'), ['name' => 'Spices'])->json('data.id');

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('window.pickMedia', false);

        $this->postJson(route('admin.products.store'), [
            'name' => 'Black Pepper',
            'sku' => 'PEP-1',
            'price' => 120,
            'category_ids' => [$spices],
            'images' => ['spices/pepper.jpg', 'spices/pepper-back.jpg'],
        ])->assertOk();

        $product = Product::firstWhere('sku', 'PEP-1');
        $this->assertSame([$spices], $product->categories->modelKeys());
        $this->assertSame('/media/spices/pepper.jpg', $product->primaryImageUrl());

        event('cms.media.relocated', [[
            'id' => 1,
            'disk' => config('cms.media.disk'),
            'from' => ['path' => 'spices/pepper.jpg'],
            'to' => ['path' => 'catalog/pepper.jpg'],
        ]]);

        $this->assertSame(['catalog/pepper.jpg', 'spices/pepper-back.jpg'], $product->fresh()->images);
    }

    public function test_the_spreadsheet_category_column_takes_comma_separated_cms_categories(): void
    {
        $this->actingAs($this->admin());
        $spices = $this->postJson(route('cms.panel.categories.store'), ['name' => 'Spices'])->json('data.id');
        $gifts = $this->postJson(route('cms.panel.categories.store'), ['name' => 'Gifts'])->json('data.id');
        $product = app(ProductCatalogService::class)->save(new Product, ['name' => 'Black Pepper', 'slug' => null, 'sku' => 'PEP-1', 'price' => 120]);

        $result = app(ProductSpreadsheetService::class)->import(
            UploadedFile::fake()->createWithContent('products.csv', "Variant SKU,Category\nPEP-1,\"spices, Gifts\"\n")
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(1, $result['updated_products']);
        $this->assertEqualsCanonicalizing([$spices, $gifts], $product->categories()->get()->modelKeys());
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_active' => true]));

        return $admin;
    }
}
