<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\CmsPage;
use Carepeerinfotech\Ecommerce\Models\Coupon;
use Carepeerinfotech\Ecommerce\Models\HomepageSection;
use Carepeerinfotech\Ecommerce\Models\HomepageSlide;
use Carepeerinfotech\Ecommerce\Models\Order;
use Carepeerinfotech\Ecommerce\Models\OrderItem;
use Carepeerinfotech\Ecommerce\Models\PaymentTransaction;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\Role;
use Carepeerinfotech\Ecommerce\Models\Shipment;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoOrderSeeder;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreContentSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_sections_point_at_seeded_categories_and_bestsellers(): void
    {
        $this->seed([StorefrontCatalogSeeder::class, HomepageContentSeeder::class]);

        $sections = HomepageSection::orderBy('sort_order')->get()->keyBy('key');

        $this->assertSame(['hero', 'categories', 'featured', 'trust'], $sections->keys()->all());
        $this->assertSame('Dressed up for every Rasam.', $sections['hero']->content['headline']);
        $this->assertSame(
            Category::whereNull('parent_id')->orderBy('sort_order')->pluck('id')->all(),
            $sections['categories']->content['category_ids'],
        );
        $this->assertEqualsCanonicalizing(
            Product::featured()->pluck('id')->all(),
            $sections['featured']->content['product_ids'],
        );
        $this->assertCount(4, $sections['featured']->content['product_ids']);
        $this->assertCount(4, $sections['trust']->content['items']);
        $this->assertTrue($sections->every(fn (HomepageSection $section) => $section->is_enabled && $section->is_published));

        $this->assertSame(
            ['/assets/images/hero-a.jpg', '/assets/images/hero-b.jpg'],
            HomepageSlide::orderBy('sort_order')->pluck('image')->all(),
        );
    }

    public function test_homepage_seeder_keeps_admin_edits(): void
    {
        $this->seed([StorefrontCatalogSeeder::class, HomepageContentSeeder::class]);
        HomepageSection::where('key', 'featured')->update(['title' => 'Edited in admin']);
        HomepageSlide::query()->first()->update(['title' => 'Edited slide']);

        $this->seed(HomepageContentSeeder::class);

        $this->assertSame(4, HomepageSection::count());
        $this->assertSame(2, HomepageSlide::count());
        $this->assertSame('Edited in admin', HomepageSection::firstWhere('key', 'featured')->title);
        $this->assertSame('Edited slide', HomepageSlide::query()->first()->title);
    }

    public function test_cms_pages_publish_store_info_and_draft_the_policies(): void
    {
        $this->seed(CmsPageSeeder::class);

        $this->assertEqualsCanonicalizing(['about-us', 'contact-us'], CmsPage::published()->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(
            ['shipping-policy', 'returns-and-exchanges', 'privacy-policy', 'terms-and-conditions'],
            CmsPage::where('status', 'draft')->pluck('slug')->all(),
        );
        $this->assertNotNull(CmsPage::firstWhere('slug', 'about-us')->published_at);
        $this->assertNull(CmsPage::firstWhere('slug', 'shipping-policy')->published_at);
        $this->assertStringContainsString('22, Katra Jaimal Singh', CmsPage::firstWhere('slug', 'contact-us')->content);
        $this->assertStringContainsString('Orders above ₹999 ship free', CmsPage::firstWhere('slug', 'shipping-policy')->content);
    }

    public function test_cms_page_seeder_keeps_admin_edits(): void
    {
        $this->seed(CmsPageSeeder::class);
        CmsPage::where('slug', 'about-us')->update(['content' => '<p>Edited in admin</p>']);

        $this->seed(CmsPageSeeder::class);

        $this->assertSame(6, CmsPage::count());
        $this->assertSame('<p>Edited in admin</p>', CmsPage::firstWhere('slug', 'about-us')->content);
    }

    public function test_demo_orders_cover_every_status_with_consistent_totals(): void
    {
        $this->seed([StorefrontCatalogSeeder::class, DemoOrderSeeder::class]);

        $orders = Order::with(['items', 'payments', 'shipments'])->get();

        $this->assertCount(17, $orders);
        $this->assertSame(
            ['cancelled' => 2, 'delivered' => 7, 'pending' => 2, 'processing' => 3, 'shipped' => 3],
            $orders->countBy('status')->sortKeys()->all(),
        );

        foreach ($orders as $order) {
            $this->assertEqualsWithDelta((float) $order->subtotal, $order->items->sum(fn (OrderItem $item) => (float) $item->total), 0.001, $order->order_number);
            $this->assertEqualsWithDelta(
                (float) $order->subtotal - (float) $order->coupon_discount + (float) $order->shipping_amount + (float) $order->tax_amount,
                (float) $order->total,
                0.001,
                $order->order_number,
            );
            $this->assertSame($order->payment_method === 'paytm' ? 1 : 0, $order->payments->count(), $order->order_number);
            $this->assertSame(in_array($order->status, ['shipped', 'delivered'], true) ? 1 : 0, $order->shipments->count(), $order->order_number);
        }

        $couponOrder = $orders->firstWhere('coupon_code', 'SHAGUN500');
        $this->assertSame('500.00', $couponOrder->coupon_discount);
        $this->assertSame('22849.00', $couponOrder->subtotal);
        $this->assertSame('0.00', $couponOrder->shipping_amount, 'free shipping above ₹999');
        $this->assertSame('4022.82', $couponOrder->tax_amount, '18% GST on ₹22,349 after the coupon');

        $this->assertSame('paid', $orders->firstWhere('order_number', 'DEMO-1002')->payment_status, 'delivered COD is collected');
        $this->assertSame('pending', $orders->firstWhere('order_number', 'DEMO-1015')->payment_status, 'undelivered COD is not');
        $this->assertSame('failed', $orders->firstWhere('order_number', 'DEMO-1006')->payment_status);
        $this->assertNull($orders->firstWhere('order_number', 'DEMO-1005')->user_id, 'guest checkout');
    }

    public function test_demo_customers_get_accounts_and_default_addresses(): void
    {
        $this->seed([StorefrontCatalogSeeder::class, DemoOrderSeeder::class]);

        $customers = User::where('is_customer', true)->with('addresses')->get();

        $this->assertCount(8, $customers);
        $this->assertTrue($customers->every(fn (User $user) => $user->email_verified_at !== null && $user->defaultShippingAddress() !== null));
        $this->assertSame(['SHAGUN500', 'WELCOME10'], Coupon::orderBy('code')->pluck('code')->all());
    }

    public function test_demo_order_seeder_can_run_again_without_duplicating_anything(): void
    {
        $this->seed([StorefrontCatalogSeeder::class, DemoOrderSeeder::class]);
        $this->seed(DemoOrderSeeder::class);

        $this->assertSame(17, Order::count());
        $this->assertSame(19, OrderItem::count());
        $this->assertSame(10, PaymentTransaction::count());
        $this->assertSame(10, Shipment::count());
        $this->assertSame(8, User::where('is_customer', true)->count());
    }

    public function test_demo_orders_are_never_seeded_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Called directly: db:seed itself stops to ask for confirmation in production.
        $this->app->make(DemoOrderSeeder::class)->run();

        $this->assertSame(0, Order::count());
        $this->assertSame(0, User::where('is_customer', true)->count());
    }

    public function test_database_seeder_builds_the_whole_store_and_can_run_again(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Role::where('slug', 'super-admin')->exists());
        $this->assertSame(58, Product::count());
        $this->assertSame(4, HomepageSection::count());
        $this->assertSame(6, CmsPage::count());
        $this->assertSame(17, Order::count());
    }
}
