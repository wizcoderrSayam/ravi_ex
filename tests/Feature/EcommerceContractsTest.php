<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\Ecommerce\Contracts\SettingsStore;
use Tests\TestCase;

/**
 * The store reads its settings through whatever the host binds to the contracts,
 * not through the CMS directly.
 */
class EcommerceContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_store_reads_its_settings_through_the_bound_settings_store(): void
    {
        $this->app->instance(SettingsStore::class, new class implements SettingsStore
        {
            /** @var array<string, mixed> */
            private array $values = ['features.orders_enabled' => false, 'features.enquiry_enabled' => true];

            public function register(string $group, string $label, array $fields, array $showWhen = []): void {}

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }

            public function siteName(): ?string
            {
                return 'Elsewhere Store';
            }

            public function mailServer(): ?array
            {
                return null;
            }

            public function notificationRecipients(): array
            {
                return [];
            }
        });

        $this->getJson(route('api.store'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Elsewhere Store')
            ->assertJsonPath('data.mode', 'enquiries')
            ->assertJsonPath('data.orders', false);
    }
}
