<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Image;
use Carepeerinfotech\Ecommerce\Models\Product;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapMenImagesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const KURTA_PAJAMA_PHOTO = '/assets/images/categories/men-kurta-pajamas.webp';

    private const WAISTCOAT_SET_PHOTO = '/assets/images/categories/men-waistcoat-sets.webp';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(StorefrontCatalogSeeder::class);
    }

    public function test_it_points_kurta_pajamas_and_their_products_at_the_kurta_pajama_photo(): void
    {
        $kurtaPajamas = Category::firstWhere('slug', 'men-kurta-pajamas');
        $products = Product::query()->where('category_id', $kurtaPajamas->id)->get();
        $this->breakImages($kurtaPajamas, $products, '/assets/images/products/men-kurta-pyjama-1.jpg');

        $this->runMigration();

        $this->assertSame(self::KURTA_PAJAMA_PHOTO, $kurtaPajamas->fresh()->imageUrl());
        $this->assertCount(9, $products);
        $this->assertSame([self::KURTA_PAJAMA_PHOTO], $this->galleryPaths($products));
    }

    public function test_it_points_waistcoat_sets_at_the_waistcoat_photo(): void
    {
        $waistcoatSets = Category::firstWhere('slug', 'men-waistcoat-sets');
        $products = Product::query()->where('category_id', $waistcoatSets->id)->get();
        $this->breakImages($waistcoatSets, $products, '/assets/images/products/men-nehru-jackets-1.jpg');

        $this->runMigration();

        $this->assertSame(self::WAISTCOAT_SET_PHOTO, $waistcoatSets->fresh()->imageUrl());
        $this->assertSame([self::WAISTCOAT_SET_PHOTO], $this->galleryPaths($products));
    }

    public function test_other_subcategories_are_left_alone(): void
    {
        $indoWesterns = Category::firstWhere('slug', 'men-indo-westerns');
        $products = Product::query()->where('category_id', $indoWesterns->id)->get();
        $this->breakImages($indoWesterns, $products, '/assets/images/products/men-sherwanis-1.jpg');

        $this->runMigration();

        $this->assertSame('/assets/images/products/men-sherwanis-1.jpg', $indoWesterns->fresh()->imageUrl());
        $this->assertSame(['/assets/images/products/men-sherwanis-1.jpg'], $this->galleryPaths($products));
    }

    public function test_admin_uploads_are_left_alone(): void
    {
        $kurtaPajamas = Category::firstWhere('slug', 'men-kurta-pajamas');
        $kurtaPajamas->images()->where('collection', 'image')->update(['path' => 'categories/admin-upload.jpg']);
        $product = Product::query()->where('category_id', $kurtaPajamas->id)->first();
        $upload = $product->images()->create([
            'collection' => 'gallery',
            'disk' => 'public',
            'path' => 'products/admin-upload.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $this->runMigration();

        $this->assertSame('categories/admin-upload.jpg', $kurtaPajamas->images()->where('collection', 'image')->value('path'));
        $this->assertSame('products/admin-upload.jpg', $upload->fresh()->path);
    }

    /**
     * Recreates the live state before the fix: the subcategory and its products show the wrong seeded photo.
     *
     * @param  iterable<Product>  $products
     */
    private function breakImages(Category $subcategory, iterable $products, string $wrongPath): void
    {
        $subcategory->images()->where('collection', 'image')->update(['path' => $wrongPath]);

        foreach ($products as $product) {
            $product->images()->where('collection', 'gallery')->update(['path' => $wrongPath]);
        }
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_16_064926_swap_men_kurta_pajama_and_waistcoat_set_images.php');

        $migration->up();
    }

    /**
     * The distinct gallery paths across the given products.
     *
     * @param  iterable<Product>  $products
     * @return list<string>
     */
    private function galleryPaths(iterable $products): array
    {
        return Image::query()
            ->where('imageable_type', (new Product)->getMorphClass())
            ->whereIn('imageable_id', collect($products)->pluck('id'))
            ->where('collection', 'gallery')
            ->distinct()
            ->pluck('path')
            ->all();
    }
}
