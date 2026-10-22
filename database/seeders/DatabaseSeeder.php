<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Peer\Ecommerce\Database\Seeders\EcommerceSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The store's roles, email templates and first admin (from ECOMMERCE_ADMIN_EMAIL
     * and ECOMMERCE_ADMIN_PASSWORD), and the demo storefront (products, media, menus, layout, components and pages).
     */
    public function run(): void
    {
        $this->call([EcommerceSeeder::class, StorefrontSeeder::class]);
    }
}
