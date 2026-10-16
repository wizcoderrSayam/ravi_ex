<?php

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUGS = ['men-kurta-pajamas', 'men-waistcoat-sets'];

    /**
     * The men's kurta pajama and waistcoat set photos were swapped: the waistcoat
     * slug image showed a kurta pajama, and kurta pajamas fell back to a product
     * photo of a waistcoat. With the files now named correctly, this points both
     * subcategories, and every product in them, at their slug image. Only seeded
     * images (under /assets/images/) are repointed; admin uploads are left alone.
     * A missing photo fails the migration so it reruns once the files are deployed.
     */
    public function up(): void
    {
        $categoryType = (new Category)->getMorphClass();
        $productType = (new Product)->getMorphClass();

        DB::transaction(function () use ($categoryType, $productType) {
            $subcategories = DB::table('categories')->whereIn('slug', self::SLUGS)->get(['id', 'slug']);

            foreach ($subcategories as $subcategory) {
                $path = "/assets/images/categories/{$subcategory->slug}.webp";

                if (! file_exists(public_path(ltrim($path, '/')))) {
                    throw new RuntimeException("Missing {$path}; deploy the image files before migrating.");
                }

                DB::table('images')
                    ->where('imageable_type', $categoryType)
                    ->where('imageable_id', $subcategory->id)
                    ->where('collection', 'image')
                    ->where('path', 'like', '/assets/images/%')
                    ->update(['path' => $path, 'updated_at' => now()]);

                DB::table('images')
                    ->where('imageable_type', $productType)
                    ->whereIn('imageable_id', DB::table('products')->where('category_id', $subcategory->id)->select('id'))
                    ->where('collection', 'gallery')
                    ->where('path', 'like', '/assets/images/%')
                    ->update(['path' => $path, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The men\'s image swap cannot be reversed.');
    }
};
