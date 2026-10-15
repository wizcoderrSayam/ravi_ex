<?php

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each new subcategory, in display order, with the old subcategories whose
     * products move into it. The first old slug is renamed in place so its id
     * and image survive; the rest are merged into it.
     *
     * @var array<string, array<string, array{name: string, from: list<string>}>>
     */
    private const SUBCATEGORIES = [
        'girls' => [
            'lehenga' => ['name' => 'Lehenga', 'from' => ['girls-lehengas']],
            'sharara-palazzo' => ['name' => 'Sharara / Palazzo', 'from' => ['girls-ethnic-sets']],
            'patiala-farshi-salwar' => ['name' => 'Patiala Salwar Suits / Farshi Salwar', 'from' => ['girls-kurta-sets']],
            'frocks-gowns' => ['name' => 'Frocks & Gowns', 'from' => ['girls-festive-gowns', 'girls-party-dresses']],
        ],
        'boys' => [
            'sherwani' => ['name' => 'Sherwani', 'from' => ['boys-sherwanis']],
            'indo-westerns' => ['name' => 'Indo-Westerns', 'from' => []],
            'coat-suits-tuxedos' => ['name' => 'Coat Suits / Tuxedos', 'from' => []],
            'jodhpuri' => ['name' => 'Jodhpuri', 'from' => []],
            'kurta-pajama' => ['name' => 'Kurta Pajama', 'from' => ['boys-kurta-sets', 'boys-pathani-suits']],
            'waistcoat-sets' => ['name' => 'Waistcoat Sets', 'from' => ['boys-wedding-wear', 'boys-jackets']],
        ],
        'men' => [
            'kurta-pajamas' => ['name' => 'Kurta Pajamas', 'from' => ['men-kurta-pyjama', 'men-pathani-suits']],
            'waistcoat-sets' => ['name' => 'Waistcoat Sets', 'from' => ['men-nehru-jackets']],
            'indo-westerns' => ['name' => 'Indo-Westerns', 'from' => ['men-indo-western', 'men-sherwanis', 'men-jodhpuri-suits']],
        ],
        'women' => [
            'co-ord-sets' => ['name' => 'Co-ord Sets', 'from' => ['women-co-ord-sets']],
        ],
    ];

    /** Retired subcategories; they are deleted along with their products. */
    private const RETIRED = [
        'girls-accessories', 'boys-shirts', 'men-shirts', 'men-jackets', 'men-trousers',
        'women-bridal-lehengas', 'women-party-lehengas', 'women-punjabi-suits',
        'women-anarkalis', 'women-kurtis', 'women-sarees',
    ];

    /** Homepage and navigation order of the top-level categories. */
    private const PARENT_ORDER = ['girls', 'boys', 'men', 'women'];

    /**
     * Replaces the kids', men's and women's subcategories with the client's new
     * list. Order items keep their snapshot because order_items.product_id is
     * nulled, not cascaded, when a product is deleted.
     */
    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::PARENT_ORDER as $position => $parentSlug) {
                DB::table('categories')->where('slug', $parentSlug)->whereNull('parent_id')->update(['sort_order' => $position]);
            }

            foreach (self::SUBCATEGORIES as $parentSlug => $children) {
                $parentId = DB::table('categories')->where('slug', $parentSlug)->whereNull('parent_id')->value('id');

                if ($parentId === null) {
                    continue;
                }

                foreach (array_keys($children) as $position => $childSlug) {
                    $this->saveSubcategory($parentId, "{$parentSlug}-{$childSlug}", $children[$childSlug], $position);
                }
            }

            $this->deleteRetired();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The storefront subcategory remap cannot be reversed.');
    }

    /**
     * @param  array{name: string, from: list<string>}  $definition
     */
    private function saveSubcategory(int $parentId, string $slug, array $definition, int $position): void
    {
        $attributes = ['name' => $definition['name'], 'parent_id' => $parentId, 'sort_order' => $position, 'is_active' => true, 'updated_at' => now()];

        $id = DB::table('categories')->where('slug', $slug)->value('id')
            ?? DB::table('categories')->whereIn('slug', $definition['from'])->orderByRaw($this->orderBySlugs($definition['from']))->value('id');

        if ($id === null) {
            $id = DB::table('categories')->insertGetId([...$attributes, 'slug' => $slug, 'created_at' => now()]);
        } else {
            DB::table('categories')->where('id', $id)->update([...$attributes, 'slug' => $slug]);
        }

        $mergedIds = DB::table('categories')->whereIn('slug', $definition['from'])->where('id', '!=', $id)->pluck('id');

        if ($mergedIds->isEmpty()) {
            return;
        }

        DB::table('products')->whereIn('category_id', $mergedIds)->update(['category_id' => $id]);
        $this->deleteCategories($mergedIds->all());
    }

    private function deleteRetired(): void
    {
        $categoryIds = DB::table('categories')->whereIn('slug', self::RETIRED)->pluck('id')->all();
        $productIds = DB::table('products')->whereIn('category_id', $categoryIds)->pluck('id')->all();
        $variantIds = DB::table('product_variants')->whereIn('product_id', $productIds)->pluck('id')->all();

        // Images are polymorphic, so deleting their owners does not cascade to them.
        DB::table('images')->where('imageable_type', (new ProductVariant)->getMorphClass())->whereIn('imageable_id', $variantIds)->delete();
        DB::table('images')->where('imageable_type', (new Product)->getMorphClass())->whereIn('imageable_id', $productIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
        $this->deleteCategories($categoryIds);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function deleteCategories(array $categoryIds): void
    {
        DB::table('images')->where('imageable_type', (new Category)->getMorphClass())->whereIn('imageable_id', $categoryIds)->delete();
        DB::table('categories')->whereIn('id', $categoryIds)->delete();
    }

    /**
     * Picks the first listed old slug when several exist, so the rename is predictable.
     *
     * @param  list<string>  $slugs
     */
    private function orderBySlugs(array $slugs): string
    {
        if ($slugs === []) {
            return 'id';
        }

        $cases = collect($slugs)->map(fn (string $slug, int $index) => 'WHEN '.DB::getPdo()->quote($slug)." THEN {$index}")->implode(' ');

        return "CASE slug {$cases} END";
    }
};
