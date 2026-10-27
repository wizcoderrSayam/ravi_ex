<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Image;
use Carepeerinfotech\Ecommerce\Models\Product;
use Database\Seeders\RaviProductListSeeder;
use Database\Seeders\StorefrontCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_backup_category_tree_and_images_without_products(): void
    {
        $this->seed(StorefrontCategorySeeder::class);

        $topLevel = Category::query()->whereNull('parent_id')->orderBy('sort_order')->get();

        $this->assertSame(['girls', 'boys', 'men', 'women'], $topLevel->pluck('slug')->all());
        $this->assertSame(14, Category::query()->whereNotNull('parent_id')->count());
        $this->assertSame(
            ['Lehenga', 'Sharara / Palazzo', 'Patiala Salwar Suits / Farshi Salwar', 'Frocks & Gowns'],
            $topLevel->firstWhere('slug', 'girls')->children->pluck('name')->all(),
        );
        $this->assertSame(
            ['Kurta Pajamas', 'Waistcoat Sets', 'Indo-Westerns'],
            $topLevel->firstWhere('slug', 'men')->children->pluck('name')->all(),
        );
        $this->assertSame('Indo-Westerns for Men | Ravi Exclusive Amritsar', Category::firstWhere('slug', 'men-indo-westerns')->meta_title);
        $this->assertSame('/assets/images/categories/boys.jpg', Category::firstWhere('slug', 'boys')->imageUrl());
        $this->assertSame('/assets/images/categories/girls-patiala-farshi-salwar.webp', Category::firstWhere('slug', 'girls-patiala-farshi-salwar')->imageUrl());

        foreach (Category::with('images')->get() as $category) {
            $this->assertNotNull($category->imageUrl(), "{$category->slug} has no image");
            $this->assertFileExists(public_path($category->imageUrl()), "{$category->slug} image is missing");
            $this->assertCount(1, $category->imagesIn('image'), "{$category->slug} should have one category image");
        }

        $this->assertSame(18, Image::query()->where('collection', 'image')->count());
        $this->assertSame(0, Product::count());
    }

    public function test_it_can_run_again_without_duplicating_categories_or_images(): void
    {
        $this->seed(StorefrontCategorySeeder::class);
        $this->seed(StorefrontCategorySeeder::class);

        $this->assertSame(18, Category::count());
        $this->assertSame(18, Image::query()->where('collection', 'image')->count());
    }

    public function test_ravi_products_use_the_backup_categories(): void
    {
        $this->seed([StorefrontCategorySeeder::class, RaviProductListSeeder::class]);

        $product = Product::firstWhere('slug', 'ivory-royal-heritage-sharara-set');

        $this->assertSame(18, Category::count());
        $this->assertSame(49, Product::count());
        $this->assertSame('girls-sharara-palazzo', $product->category->slug);
        $this->assertSame('girls', $product->category->parent->slug);
        $this->assertSame('Lehengas, sharara and palazzo suits, Patiala salwar suits, frocks and gowns for girls aged 1 to 14, from their first festive outfit to the big family wedding.', $product->category->parent->description);
    }
}
