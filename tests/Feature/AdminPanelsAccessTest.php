<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;

class AdminPanelsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_from_the_cms_to_the_store_login(): void
    {
        $this->get(route('cms.panel.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_login_page_uses_the_cms_panel_theme(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('class="cms-theme"', false)
            ->assertSee('--cms-accent', false)
            ->assertSee(route('admin.login.submit'), false);
    }

    public function test_customers_cannot_open_the_cms(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cms.panel.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_store_and_cms_screens_share_the_cms_panel_and_its_store_menu(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::create(['name' => 'Admin', 'slug' => 'admin', 'is_active' => true]));

        foreach ([route('admin.dashboard'), route('cms.panel.dashboard')] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('id="cms-sidebar"', false)
                ->assertSee(route('admin.orders.index'), false);
        }
    }
}
