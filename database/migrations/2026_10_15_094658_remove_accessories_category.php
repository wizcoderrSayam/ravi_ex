<?php

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The client's category list has no Accessories, so the top-level category,
     * its subcategories and their products are deleted. Order items keep their
     * snapshot because order_items.product_id is nulled, not cascaded.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $parentId = DB::table('categories')->where('slug', 'accessories')->whereNull('parent_id')->value('id');

            if ($parentId === null) {
                return;
            }

            $categoryIds = DB::table('categories')->where('parent_id', $parentId)->pluck('id')->push($parentId)->all();
            $productIds = DB::table('products')->whereIn('category_id', $categoryIds)->pluck('id')->all();
            $variantIds = DB::table('product_variants')->whereIn('product_id', $productIds)->pluck('id')->all();

            // Images are polymorphic, so deleting their owners does not cascade to them.
            DB::table('images')->where('imageable_type', (new ProductVariant)->getMorphClass())->whereIn('imageable_id', $variantIds)->delete();
            DB::table('images')->where('imageable_type', (new Product)->getMorphClass())->whereIn('imageable_id', $productIds)->delete();
            DB::table('images')->where('imageable_type', (new Category)->getMorphClass())->whereIn('imageable_id', $categoryIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
            DB::table('categories')->whereIn('id', $categoryIds)->delete();

            $this->removeFromHomepageCategories($parentId);
        });
    }

    /** The homepage "categories" section lists top-level category ids as JSON. */
    private function removeFromHomepageCategories(int $categoryId): void
    {
        $section = DB::table('homepage_sections')->where('key', 'categories')->first();

        if ($section === null) {
            return;
        }

        $content = json_decode($section->content, true) ?? [];
        $content['category_ids'] = array_values(array_diff($content['category_ids'] ?? [], [$categoryId]));

        DB::table('homepage_sections')->where('id', $section->id)->update(['content' => json_encode($content)]);
    }

    public function down(): void
    {
        throw new RuntimeException('Removing the Accessories category cannot be reversed.');
    }
};
