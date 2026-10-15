<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Order;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_do_not_include_cms_pages(): void
    {
        $this->seed(EcommerceSeeder::class);

        // fresh() loads is_active's database default, which the admin middleware checks.
        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('<p class="text-sm text-slate-500">Products</p>', false);
        $response->assertSee('<p class="text-sm text-slate-500">Revenue</p>', false);
        $response->assertDontSee('<p class="text-sm text-slate-500">CMS Pages</p>', false);
    }

    public function test_admin_pages_render_the_seeded_store(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));
        $this->actingAs($admin);

        $shippedOrder = Order::firstWhere('status', 'shipped');
        $product = Product::firstWhere('name', 'Festive Sherwani');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('DEMO-1017');
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('DEMO-1017');
        $this->get(route('admin.orders.show', $shippedOrder))->assertOk()->assertSee($shippedOrder->courier_name)->assertSee('In Transit');
        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('6203')->assertSee('/assets/images/categories/men-indo-westerns.webp', false);
        $this->get(route('admin.categories.edit', Category::firstWhere('slug', 'men')))->assertOk()->assertSee('/assets/images/categories/men.jpg', false);
        $this->get(route('admin.homepage.edit'))->assertOk()->assertSee('Dressed up for every Rasam.');
        $this->get(route('admin.pages.index'))->assertOk()->assertSee('Returns &amp; Exchanges', false);
    }
}
