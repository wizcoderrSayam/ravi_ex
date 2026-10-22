<?php

namespace Tests\Feature;

use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\CMS\Facades\CMS;
use Tests\TestCase;

class DebugbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cms_debug_mode_turns_the_debugbar_on_and_off(): void
    {
        config(['app.debug' => false, 'debugbar.force_allow_enable' => true]);
        $debugbar = $this->app->make(LaravelDebugbar::class);

        CMS::settings()->set('app.debug', true);
        $this->get(route('admin.login'))->assertOk();
        $this->assertTrue($debugbar->isEnabled());

        CMS::settings()->set('app.debug', false);
        $this->get(route('admin.login'))->assertOk();
        $this->assertFalse($debugbar->isEnabled());
    }

    public function test_the_debugbar_stays_off_where_it_is_not_allowed_even_with_debug_mode_on(): void
    {
        config(['debugbar.force_allow_enable' => false]);
        $debugbar = $this->app->make(LaravelDebugbar::class);

        CMS::settings()->set('app.debug', true);
        $this->get(route('admin.login'))->assertOk();
        $this->assertFalse($debugbar->isEnabled());
    }

    public function test_the_debugbar_may_boot_in_production_but_not_in_tests(): void
    {
        $this->assertFalse(config('debugbar.force_allow_enable'));
        $this->assertTrue($this->debugbarConfigIn('production')['force_allow_enable']);
    }

    /**
     * config/debugbar.php as it loads with APP_ENV set to the given environment.
     *
     * @return array<string, mixed>
     */
    private function debugbarConfigIn(string $environment): array
    {
        $previous = $_SERVER['APP_ENV'] ?? null;
        $_SERVER['APP_ENV'] = $environment;

        try {
            return require config_path('debugbar.php');
        } finally {
            if ($previous === null) {
                unset($_SERVER['APP_ENV']);
            } else {
                $_SERVER['APP_ENV'] = $previous;
            }
        }
    }
}
