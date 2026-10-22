<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\Ecommerce\Services\Shipping\ShippingManager;
use Peer\Ecommerce\Support\StoreSettings;
use Tests\TestCase;

/**
 * Covers `move_shiprocket_driver_to_shipping_provider`: a store that shipped live
 * with Shiprocket keeps doing so once the Shipping section picks the provider.
 */
class ShiprocketDriverMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require base_path('packages/ecommerce/database/migrations/2026_10_20_000002_move_shiprocket_driver_to_shipping_provider.php');
    }

    public function test_live_shiprocket_becomes_the_shipping_provider(): void
    {
        StoreSettings::set('shiprocket.driver', 'live');

        $this->migration()->up();

        $this->assertSame('shiprocket', app(ShippingManager::class)->driverName());
    }

    public function test_live_shiprocket_switched_off_leaves_the_default_provider(): void
    {
        StoreSettings::set('shiprocket.enabled', '0');
        StoreSettings::set('shiprocket.driver', 'live');

        $this->migration()->up();

        $this->assertSame('fake', app(ShippingManager::class)->driverName());
    }

    public function test_fake_shiprocket_leaves_the_default_provider(): void
    {
        StoreSettings::set('shiprocket.driver', 'fake');

        $this->migration()->up();

        $this->assertSame('fake', app(ShippingManager::class)->driverName());
    }
}
