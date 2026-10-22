<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cms_serves_the_seeded_home_page_at_the_root(): void
    {
        Storage::fake(config('cms.media.disk'));
        $this->seed();

        $this->get('/')->assertOk()->assertSee('Celebrating Every Occasion, the <em>Traditional</em> Way.', false);
    }
}
