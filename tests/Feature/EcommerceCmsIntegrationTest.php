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
use Peer\Ecommerce\Services\Mail\TemplateMailer;
use Peer\Ecommerce\Services\Shipping\ShippingManager;
use Peer\Ecommerce\Services\Shipping\ShiprocketProvider;
use Tests\TestCase;

class EcommerceCmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_are_sections_of_the_cms_settings_read_typed(): void
    {
        $this->assertSame(49, CMS::settings()->get('shipping.flat_rate'));
        $this->assertTrue(CMS::settings()->get('shipping.charges_enabled'));

        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertOk()
            ->assertViewHas('config', fn (array $config): bool => collect($config['sections'])->pluck('key')->all() === [
                'general', 'app', 'notifications', 'smtp', 'commerce', 'shipping', 'shipping_provider', 'shiprocket', 'features',
            ]);

        $this->putJson(route('cms.panel.settings.update'), [
            'shipping' => ['flat_rate' => '59.5', 'charges_enabled' => false],
        ])->assertOk();

        $this->assertSame(59.5, CMS::settings()->get('shipping.flat_rate'));
        $this->assertFalse(CMS::settings()->get('shipping.charges_enabled'));
    }

    public function test_the_store_notification_switches_follow_the_cms_recipients_in_one_section(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertOk()
            ->assertViewHas('config', function (array $config): bool {
                $fields = collect(collect($config['sections'])->firstWhere('key', 'notifications')['fields'])->pluck('key');

                return $fields->take(4)->all() === ['to', 'cc', 'bcc', 'enabled'] && ! $fields->contains('admin_email');
            });
    }

    public function test_admin_copies_go_to_everyone_on_the_notification_recipients_once(): void
    {
        CMS::settings()->set('commerce.support_email', 'help@example.com');
        CMS::settings()->set('notifications.to', 'owner@example.com, sales@example.com');
        CMS::settings()->set('notifications.bcc', 'owner@example.com, audit@example.com');

        $recipients = app(TemplateMailer::class)->adminRecipients();

        $this->assertSame(['owner@example.com', 'sales@example.com', 'audit@example.com'], $recipients);
    }

    public function test_admin_copies_go_to_the_support_email_while_there_are_no_notification_recipients(): void
    {
        CMS::settings()->set('commerce.support_email', 'help@example.com');

        $recipients = app(TemplateMailer::class)->adminRecipients();

        $this->assertSame(['help@example.com'], $recipients);
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

    public function test_the_host_adds_sidebar_items_beside_and_inside_the_stores(): void
    {
        config(['cms.panel.navigation' => [
            'tours' => ['label' => 'Tours', 'url' => '/tours', 'sort' => 120],
            'store.reviews' => ['label' => 'Reviews', 'url' => '/reviews'],
        ]]);

        $this->actingAs($this->admin())
            ->get(route('cms.panel.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['title="Store"', 'title="Email templates"', 'title="Reviews"', 'title="Tours"', 'title="CMS"'], false);
    }

    public function test_a_store_settings_action_answers_with_the_message_the_cms_shows(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-shipping', 'fake'))
            ->assertExactJson(['success' => true, 'message' => 'Fake Shiprocket provider is ready.']);
    }

    public function test_testing_a_courier_missing_from_the_config_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.settings.test-shipping', 'pigeon'))
            ->assertNotFound();
    }

    public function test_a_couriers_settings_show_inside_the_shipping_provider_section_while_it_is_picked(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertOk()
            ->assertViewHas('config', function (array $config): bool {
                $shiprocket = collect($config['sections'])->firstWhere('key', 'shiprocket');

                return $shiprocket['label'] === 'Shiprocket'
                    && $shiprocket['show_when'] === ['group' => 'shipping_provider', 'key' => 'driver', 'values' => ['shiprocket']]
                    && collect($shiprocket['fields'])->pluck('key')->all() === [...array_keys(app(ShiprocketProvider::class)->settings()), 'test']
                    && collect($shiprocket['fields'])->firstWhere('key', 'test')['url'] === route('admin.settings.test-shipping', 'shiprocket');
            });
    }

    public function test_the_shipping_provider_is_picked_from_the_configured_couriers_and_saved_with_its_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cms.panel.settings.index'))
            ->assertViewHas('config', function (array $config): bool {
                $provider = collect($config['sections'])->firstWhere('key', 'shipping_provider');

                return $config['settings']['shipping_provider']['driver'] === 'fake'
                    && collect($provider['fields'])->firstWhere('key', 'driver')['options'] === [
                        ['value' => 'shiprocket', 'label' => 'Shiprocket'],
                        ['value' => 'fake', 'label' => 'Fake'],
                    ];
            });

        $this->putJson(route('cms.panel.settings.update'), ['shipping_provider' => ['driver' => 'pigeon']])->assertInvalid(['shipping_provider.driver']);
        $this->putJson(route('cms.panel.settings.update'), [
            'shipping_provider' => ['driver' => 'shiprocket'],
            'shiprocket' => ['email' => 'ops@example.com'],
        ])->assertOk();

        $this->assertInstanceOf(ShiprocketProvider::class, app(ShippingManager::class)->driver());
        $this->assertSame('ops@example.com', CMS::settings()->get('shiprocket.email'));
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
