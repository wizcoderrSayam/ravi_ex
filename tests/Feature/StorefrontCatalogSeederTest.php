<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Image;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_storefront_category_tree(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $topLevel = Category::query()->whereNull('parent_id')->orderBy('sort_order')->get();

        $this->assertSame(['boys', 'girls', 'women', 'men', 'accessories'], $topLevel->pluck('slug')->all());
        $this->assertSame(33, Category::query()->whereNotNull('parent_id')->count());

        $men = $topLevel->firstWhere('slug', 'men');
        $this->assertSame('/assets/images/categories/men.jpg', $men->imageUrl());
        $this->assertSame('Sherwanis', $men->children->first()->name);
        $this->assertSame('men-sherwanis', $men->children->first()->slug);
        $this->assertTrue(Category::firstWhere('slug', 'boys-sherwanis')->parent->is($topLevel->firstWhere('slug', 'boys')));
    }

    public function test_it_seeds_every_storefront_product_with_an_existing_image(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $products = Product::with(['category', 'images'])->get();

        $this->assertCount(129, $products);
        $this->assertSame(129, $products->pluck('slug')->unique()->count());
        $this->assertSame(4, $products->where('is_featured', true)->count());

        foreach ($products as $product) {
            $this->assertNotNull($product->category->parent_id, "{$product->name} should sit in a subcategory");
            $this->assertFileExists(public_path($product->primaryImageUrl()), "{$product->name} image is missing");
        }
    }

    public function test_sized_products_get_a_variant_per_size(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $sherwani = Product::with(['category', 'options.values', 'variants'])->firstWhere('name', 'Festive Sherwani');

        $this->assertSame('men-sherwanis', $sherwani->category->slug);
        $this->assertTrue($sherwani->has_variants);
        $this->assertSame('RE-0035-S', $sherwani->sku);
        $this->assertSame('5399.00', $sherwani->price);
        $this->assertSame('7199.00', $sherwani->compare_price);
        $this->assertSame('Fabric: Silk Blend', $sherwani->short_description);
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL', '3XL'], $sherwani->options->first()->values->pluck('value')->all());
        $this->assertSame(['Size' => '3XL'], $sherwani->variants->firstWhere('sku', 'RE-0035-3XL')->option_values);
        $this->assertSame(60, $sherwani->stock);
    }

    public function test_free_size_products_get_a_single_default_variant(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $safa = Product::with('variants')->firstWhere('name', 'Velvet Safa');

        $this->assertFalse($safa->has_variants);
        $this->assertNull($safa->compare_price);
        $this->assertCount(1, $safa->variants);
        $this->assertSame('Free Size', $safa->variants->first()->option_label);
        $this->assertSame('RE-0071', $safa->variants->first()->sku);
    }

    public function test_products_get_tax_shipping_and_seo_details(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $sherwani = Product::with('variants')->firstWhere('name', 'Festive Sherwani');

        $this->assertSame('6203', $sherwani->hsn_code);
        $this->assertSame('1.800', $sherwani->weight);
        $this->assertSame(['40.00', '32.00', '10.00'], [$sherwani->length, $sherwani->breadth, $sherwani->height]);
        $this->assertSame('1.800', $sherwani->variants->first()->weight);
        $this->assertStringContainsString('Festive Sherwani in Silk Blend, from our Sherwanis collection.', $sherwani->description);
        $this->assertStringContainsString('Sizes: S, M, L, XL, XXL, 3XL', $sherwani->description);
        $this->assertStringContainsString('Care: Dry clean only.', $sherwani->description);
        $this->assertSame('Festive Sherwani | Sherwanis for Men | Ravi Exclusive', $sherwani->meta_title);
        $this->assertLessThanOrEqual(160, mb_strlen($sherwani->meta_description));

        $this->assertSame(0, Product::query()->where(fn ($query) => $query
            ->whereNull('hsn_code')->orWhereNull('weight')->orWhereNull('description')
            ->orWhereNull('meta_title')->orWhereNull('meta_description'))->count());
    }

    public function test_accessories_describe_a_material_rather_than_a_fabric(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $safa = Product::firstWhere('name', 'Velvet Safa');

        $this->assertSame('Material: Polki', $safa->short_description);
        $this->assertStringContainsString('Sizes: Free size', $safa->description);
        $this->assertSame('Velvet Safa | Turbans & Safas | Ravi Exclusive', $safa->meta_title);
    }

    public function test_categories_get_descriptions_and_seo_details(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $this->assertSame(0, Category::query()->where(fn ($query) => $query
            ->whereNull('description')->orWhereNull('meta_title')->orWhereNull('meta_description'))->count());
        $this->assertSame('Sherwanis for Men | Ravi Exclusive Amritsar', Category::firstWhere('slug', 'men-sherwanis')->meta_title);
    }

    public function test_every_category_has_an_image_and_every_subcategory_has_products(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        foreach (Category::with('images')->withCount('products')->get() as $category) {
            $this->assertNotNull($category->imageUrl(), "{$category->slug} has no image");
            $this->assertFileExists(public_path($category->imageUrl()), "{$category->slug} image is missing");

            if ($category->parent_id !== null) {
                $this->assertGreaterThanOrEqual(1, $category->products_count, "{$category->slug} has no products");
            }
        }

        $this->assertSame(
            '/assets/images/products/men-sherwanis-1.jpg',
            Category::firstWhere('slug', 'men-sherwanis')->imageUrl(),
            "a subcategory shows its first product's photo",
        );
        $this->assertSame(3, Category::firstWhere('slug', 'girls-lehengas')->products()->count());
    }

    public function test_it_can_run_again_without_duplicating_anything(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);
        $this->seed(StorefrontCatalogSeeder::class);

        $this->assertSame(38, Category::count());
        $this->assertSame(129, Product::count());
        $this->assertSame(129 + 5 + 33, Image::count(), 'one per product, top-level category and subcategory');
        $this->assertSame(Product::count(), ProductVariant::query()->distinct()->count('product_id'));
    }
}
