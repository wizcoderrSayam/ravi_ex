<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cms_serves_the_seeded_home_page_at_the_root(): void
    {
        $this->seed();

        $this->get('/')->assertOk()->assertSee('Everyday essentials', false);
    }
}
