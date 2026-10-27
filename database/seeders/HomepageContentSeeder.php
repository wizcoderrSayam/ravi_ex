<?php

namespace Database\Seeders;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\HomepageSection;
use Carepeerinfotech\Ecommerce\Models\HomepageSlide;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Fills the admin's Homepage editor and slides with the demo site's home page
 * content. Run after StorefrontCatalogSeeder, since the category and featured
 * sections point at seeded rows. Safe to re-run: existing sections and slides
 * are left alone, so edits made in the admin survive.
 */
class HomepageContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSections();
        $this->seedSlides();
    }

    private function seedSections(): void
    {
        $sections = [
            'hero' => [
                'title' => null,
                'content' => [
                    'brand' => 'Ravi Exclusive',
                    'headline' => 'Dressed up for every Rasam.',
                    'subline' => 'Hand-embroidered lehengas, statement suits and festive silhouettes for every celebration. Fresh in at our Katra Jaimal Singh store.',
                    'cta_label' => 'Shop Women',
                    'cta_url' => '/women',
                    'image' => '/assets/images/hero-a.jpg',
                ],
            ],
            'categories' => [
                'title' => 'For the whole parivaar',
                'content' => [
                    'category_ids' => Category::query()
                        ->whereNull('parent_id')
                        ->orderBy('sort_order')
                        ->pluck('id')
                        ->all(),
                ],
            ],
            'featured' => [
                'title' => 'Bestsellers',
                'content' => [
                    'product_ids' => Product::query()->featured()->orderBy('id')->pluck('id')->all(),
                ],
            ],
            'trust' => [
                'title' => null,
                'content' => [
                    'items' => [
                        ['title' => 'Amritsari style', 'text' => 'Handpicked from Katra Jaimal Singh'],
                        ['title' => 'Fitting & alterations', 'text' => 'Ask us before you order'],
                        ['title' => 'Order on WhatsApp', 'text' => 'Personal service, ji'],
                        ['title' => 'Aao ji, visit us', 'text' => ' 22, Katra Jaimal Singh, Amritsar'],
                    ],
                ],
            ],
        ];

        foreach (array_keys($sections) as $position => $key) {
            HomepageSection::firstOrCreate(['key' => $key], [
                ...$sections[$key],
                'type' => $key,
                'sort_order' => $position + 1,
                'is_enabled' => true,
                'is_published' => true,
            ]);
        }
    }

    /**
     * Both demo hero photos are portrait shots of girls' festive wear.
     */
    private function seedSlides(): void
    {
        $slides = [
            ['title' => 'Festive sharara sets for girls', 'link_url' => '/girls#ethnic-sets', 'image' => '/assets/images/hero-a.jpg'],
            ['title' => 'Frock-style suits for little divas', 'link_url' => '/girls#kurta-sets', 'image' => '/assets/images/hero-b.jpg'],
        ];

        foreach ($slides as $position => $slide) {
            HomepageSlide::firstOrCreate(['image' => $slide['image']], [
                ...$slide,
                'sort_order' => $position,
                'is_active' => true,
            ]);
        }
    }
}
