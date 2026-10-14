<?php

namespace Database\Seeders;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder here is safe to re-run.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
        ]);

        // The package seeder resets store settings to its defaults, so only run it
        // on a fresh install; afterwards, settings belong to the admin.
        if (! Role::query()->exists()) {
            $this->call(EcommerceSeeder::class);
        }

        $this->call([
            StorefrontCatalogSeeder::class,
            HomepageContentSeeder::class,
            CmsPageSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoOrderSeeder::class);
        }
    }
}
