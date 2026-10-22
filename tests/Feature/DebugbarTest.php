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
}
