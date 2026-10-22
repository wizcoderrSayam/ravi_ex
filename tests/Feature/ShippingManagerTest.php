<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Peer\Ecommerce\Services\Shipping\FakeShiprocketProvider;
use Peer\Ecommerce\Services\Shipping\ShippingManager;
use Peer\Ecommerce\Services\Shipping\ShiprocketProvider;
use Peer\Ecommerce\Support\StoreSettings;
use Tests\TestCase;

/**
 * Orders ship with the courier picked in the Shipping provider settings, else the one
 * config('shipping.driver') names.
 */
class ShippingManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_until_a_provider_is_picked_orders_ship_with_the_configured_default(): void
    {
        config(['shipping.driver' => 'shiprocket']);

        $driver = app(ShippingManager::class)->driver();

        $this->assertInstanceOf(ShiprocketProvider::class, $driver);
    }

    public function test_the_picked_provider_wins_over_the_configured_default(): void
    {
        config(['shipping.driver' => 'shiprocket']);
        StoreSettings::set('shipping_provider.driver', 'fake');

        $driver = app(ShippingManager::class)->driver();

        $this->assertInstanceOf(FakeShiprocketProvider::class, $driver);
    }

    public function test_the_fake_driver_ships_without_settings_to_fill_in(): void
    {
        $driver = app(ShippingManager::class)->driver('fake');

        $this->assertSame([], $driver->settings());
    }

    public function test_a_driver_missing_from_the_config_is_rejected(): void
    {
        StoreSettings::set('shipping_provider.driver', 'pigeon');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipping driver [pigeon]');

        app(ShippingManager::class)->driver();
    }

    public function test_a_driver_that_is_not_a_shipping_provider_is_rejected(): void
    {
        config(['shipping.drivers.pigeon' => self::class]);

        $this->expectException(InvalidArgumentException::class);

        app(ShippingManager::class)->driver('pigeon');
    }
}
