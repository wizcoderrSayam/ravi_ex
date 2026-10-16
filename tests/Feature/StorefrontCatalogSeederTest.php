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

        $this->assertSame(['girls', 'boys', 'men', 'women'], $topLevel->pluck('slug')->all());
        $this->assertSame(14, Category::query()->whereNotNull('parent_id')->count());

        $men = $topLevel->firstWhere('slug', 'men');
        $this->assertSame('/assets/images/categories/men.jpg', $men->imageUrl());
        $this->assertSame(['Kurta Pajamas', 'Waistcoat Sets', 'Indo-Westerns'], $men->children->pluck('name')->all());
        $this->assertSame('men-kurta-pajamas', $men->children->first()->slug);
        $this->assertTrue(Category::firstWhere('slug', 'boys-sherwani')->parent->is($topLevel->firstWhere('slug', 'boys')));
    }

    public function test_it_seeds_every_storefront_product_with_an_existing_image(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $products = Product::with(['category', 'images'])->get();

        $this->assertCount(67, $products);
        $this->assertSame(67, $products->pluck('slug')->unique()->count());
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

        $this->assertSame('men-indo-westerns', $sherwani->category->slug);
        $this->assertTrue($sherwani->has_variants);
        $this->assertSame('RE-0035-S', $sherwani->sku);
        $this->assertSame('5399.00', $sherwani->price);
        $this->assertSame('7199.00', $sherwani->compare_price);
        $this->assertSame('Fabric: Silk Blend', $sherwani->short_description);
        $this->assertSame(['S', 'M', 'L', 'XL', 'XXL', '3XL'], $sherwani->options->first()->values->pluck('value')->all());
        $this->assertSame(['Size' => '3XL'], $sherwani->variants->firstWhere('sku', 'RE-0035-3XL')->option_values);
        $this->assertSame(60, $sherwani->stock);
    }

    public function test_products_get_tax_shipping_and_seo_details(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $sherwani = Product::with('variants')->firstWhere('name', 'Festive Sherwani');

        $this->assertSame('6203', $sherwani->hsn_code);
        $this->assertSame('1.600', $sherwani->weight);
        $this->assertSame(['40.00', '32.00', '8.00'], [$sherwani->length, $sherwani->breadth, $sherwani->height]);
        $this->assertSame('1.600', $sherwani->variants->first()->weight);
        $this->assertStringContainsString('Festive Sherwani in Silk Blend, from our Indo-Westerns collection.', $sherwani->description);
        $this->assertStringContainsString('Sizes: S, M, L, XL, XXL, 3XL', $sherwani->description);
        $this->assertStringContainsString('Care: Dry clean only.', $sherwani->description);
        $this->assertSame('Festive Sherwani | Indo-Westerns for Men | Ravi Exclusive', $sherwani->meta_title);
        $this->assertLessThanOrEqual(160, mb_strlen($sherwani->meta_description));

        $this->assertSame(0, Product::query()->where(fn ($query) => $query
            ->whereNull('hsn_code')->orWhereNull('weight')->orWhereNull('description')
            ->orWhereNull('meta_title')->orWhereNull('meta_description'))->count());
    }

    public function test_categories_get_descriptions_and_seo_details(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $this->assertSame(0, Category::query()->where(fn ($query) => $query
            ->whereNull('description')->orWhereNull('meta_title')->orWhereNull('meta_description'))->count());
        $this->assertSame('Indo-Westerns for Men | Ravi Exclusive Amritsar', Category::firstWhere('slug', 'men-indo-westerns')->meta_title);
    }

    public function test_every_category_has_an_image(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        foreach (Category::with('images')->get() as $category) {
            $this->assertNotNull($category->imageUrl(), "{$category->slug} has no image");
            $this->assertFileExists(public_path($category->imageUrl()), "{$category->slug} image is missing");
        }

        $this->assertSame('/assets/images/categories/men-indo-westerns.webp', Category::firstWhere('slug', 'men-indo-westerns')->imageUrl());
        $this->assertSame('/assets/images/categories/men-kurta-pajamas.webp', Category::firstWhere('slug', 'men-kurta-pajamas')->imageUrl());
        $this->assertSame('/assets/images/categories/men-waistcoat-sets.webp', Category::firstWhere('slug', 'men-waistcoat-sets')->imageUrl());
        $this->assertSame(3, Category::firstWhere('slug', 'girls-lehenga')->products()->count());
    }

    public function test_products_share_their_subcategory_slug_image(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        $this->assertSame('/assets/images/categories/boys-jodhpuri.webp', Category::firstWhere('slug', 'boys-jodhpuri')->imageUrl());

        $indoWesterns = Product::with('images')->whereRelation('category', 'slug', 'men-indo-westerns')->get();
        $this->assertCount(15, $indoWesterns);
        $this->assertSame(['/assets/images/categories/men-indo-westerns.webp'], $indoWesterns->map->primaryImageUrl()->unique()->values()->all());

        $kurtaPajamas = Product::with('images')->whereRelation('category', 'slug', 'men-kurta-pajamas')->get();
        $this->assertCount(9, $kurtaPajamas);
        $this->assertSame(['/assets/images/categories/men-kurta-pajamas.webp'], $kurtaPajamas->map->primaryImageUrl()->unique()->values()->all());
    }

    public function test_every_subcategory_has_products_showing_its_photo(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);

        foreach (Category::query()->whereNotNull('parent_id')->with('products.images')->get() as $subcategory) {
            $this->assertNotEmpty($subcategory->products, "{$subcategory->slug} has no products");
        }

        foreach (['boys-indo-westerns', 'boys-coat-suits-tuxedos', 'boys-jodhpuri'] as $slug) {
            $products = Product::with('images')->whereRelation('category', 'slug', $slug)->get();
            $this->assertCount(3, $products, "{$slug} has three products");
            $this->assertSame(["/assets/images/categories/{$slug}.webp"], $products->map->primaryImageUrl()->unique()->values()->all());
        }
    }

    public function test_it_can_run_again_without_duplicating_anything(): void
    {
        $this->seed(StorefrontCatalogSeeder::class);
        $this->seed(StorefrontCatalogSeeder::class);

        $this->assertSame(18, Category::count());
        $this->assertSame(67, Product::count());
        $this->assertSame(67 + 4 + 14, Image::count(), 'one per product, top-level category and subcategory');
        $this->assertSame(Product::count(), ProductVariant::query()->distinct()->count('product_id'));
    }
}
