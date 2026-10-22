<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Peer\CMS\Facades\CMS;
use Peer\Ecommerce\Models\Enquiry;
use Peer\Ecommerce\Models\Role;
use Peer\Ecommerce\Support\Features;
use Tests\TestCase;

class StorefrontFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_store_takes_orders_and_shows_prices_and_stock_until_switched(): void
    {
        $this->assertTrue(Features::orders());
        $this->assertFalse(Features::enquiry());
        $this->assertTrue(Features::pricing());
        $this->assertTrue(Features::stock());
        $this->assertSame(Features::MODE_ORDERS, Features::mode());

        CMS::settings()->set('features.orders_enabled', false);
        CMS::settings()->set('features.enquiry_enabled', true);

        $this->assertSame(Features::MODE_ENQUIRIES, Features::mode());
        $this->assertSame('Send Enquiry', Features::checkoutLabel());
        $this->assertSame('shop.enquiry', Features::checkoutRoute());
    }

    public function test_the_enquiries_screen_follows_its_flag_in_the_panel_menu(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_active' => true]));

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee(route('admin.enquiries.index'), false);

        CMS::settings()->set('features.enquiry_enabled', true);
        $enquiry = Enquiry::create(['customer_name' => 'Asha', 'customer_email' => 'asha@example.com']);

        $this->get(route('admin.dashboard'))->assertSee(route('admin.enquiries.index'), false);
        $this->get(route('admin.enquiries.index'))->assertOk()->assertSee($enquiry->enquiry_number);
        $this->assertStringStartsWith('ENQ-', $enquiry->enquiry_number);
    }

    public function test_the_feature_middleware_hides_a_route_while_its_flag_is_off(): void
    {
        Route::get('/quote', fn () => 'ok')->middleware('feature:enquiry_enabled');

        $this->get('/quote')->assertNotFound();

        CMS::settings()->set('features.enquiry_enabled', true);

        $this->get('/quote')->assertOk();
    }
}
