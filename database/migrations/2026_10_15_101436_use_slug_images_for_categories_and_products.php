<?php

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Points each subcategory, and every product in it, at the client's photo
     * named after the subcategory slug (public/assets/images/categories/{slug}.webp).
     * Subcategories without a photo yet keep their current images. Only image
     * rows change; product details edited in the admin are left alone.
     */
    public function up(): void
    {
        $categoryType = (new Category)->getMorphClass();
        $productType = (new Product)->getMorphClass();

        DB::transaction(function () use ($categoryType, $productType) {
            $subcategories = DB::table('categories')->whereNotNull('parent_id')->get(['id', 'slug']);

            foreach ($subcategories as $subcategory) {
                $path = "/assets/images/categories/{$subcategory->slug}.webp";

                if (! file_exists(public_path(ltrim($path, '/')))) {
                    continue;
                }

                DB::table('images')
                    ->where('imageable_type', $categoryType)
                    ->where('imageable_id', $subcategory->id)
                    ->where('collection', 'image')
                    ->update(['path' => $path, 'updated_at' => now()]);

                $products = DB::table('products')->where('category_id', $subcategory->id)->get(['id', 'name']);

                DB::table('images')
                    ->where('imageable_type', $productType)
                    ->whereIn('imageable_id', $products->pluck('id'))
                    ->where('collection', 'gallery')
                    ->delete();

                DB::table('images')->insert($products->map(fn (object $product): array => [
                    'imageable_type' => $productType,
                    'imageable_id' => $product->id,
                    'collection' => 'gallery',
                    'disk' => 'public',
                    'path' => $path,
                    'alt' => $product->name,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The slug image switch cannot be reversed.');
    }
};
