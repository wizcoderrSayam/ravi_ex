<?php

namespace Database\Seeders;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Services\Catalog\ProductCatalogService;
use Carepeerinfotech\Ecommerce\Services\Media\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the categories and products shown on the static storefront
 * (peerdemo.in/ravi). Safe to re-run: rows are matched by slug and updated.
 *
 * Images are referenced in place from public/assets/images, so no files are
 * copied to the public disk. New-arrival products get a recent created_at,
 * and bestsellers are marked featured.
 */
class StorefrontCatalogSeeder extends Seeder
{
    private const MENS_SIZES = ['S', 'M', 'L', 'XL', 'XXL', '3XL'];

    private const WOMENS_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    private const KIDS_SIZES = ['2Y', '4Y', '6Y', '8Y', '10Y', '12Y', '14Y'];

    private const STOCK_PER_VARIANT = 10;

    /** Str::limit() appends "...", keeping meta descriptions within the 160 characters search results show. */
    private const META_DESCRIPTION_LIMIT = 157;

    /** @var array<string, true> */
    private array $usedProductSlugs = [];

    public function __construct(
        private ProductCatalogService $catalog,
        private ImageService $images,
    ) {}

    public function run(): void
    {
        DB::transaction(function () {
            $subcategories = $this->seedCategories();

            foreach ($this->products() as $product) {
                $this->seedProduct($product, ...$subcategories[$product['category']]);
            }

            // A subcategory shows its own slug image; without one, its first product's photo, or its parent's until it has products.
            $firstProductImages = collect($this->products())->unique('category')->pluck('image', 'category');

            foreach ($subcategories as $path => [$subcategory, $profile]) {
                $image = $this->slugImage($subcategory->slug)
                    ?? $firstProductImages[$path]
                    ?? 'categories/'.Str::before($path, '/').'.jpg';
                $this->syncImage($subcategory, 'image', '/assets/images/'.$image, $profile['name']);
            }
        });
    }

    /**
     * Each subcategory with its profile and its parent's name, keyed by "parent/child" slug path.
     *
     * @return array<string, array{Category, array{name: string, hsn: string, weight: float, box: array{int, int, int}, blurb: string, care: string}, string}>
     */
    private function seedCategories(): array
    {
        $subcategories = [];

        foreach (array_keys($this->categories()) as $position => $parentSlug) {
            ['name' => $name, 'description' => $description, 'children' => $children] = $this->categories()[$parentSlug];

            $parent = $this->saveCategory($parentSlug, $position, null, [
                'name' => $name,
                'description' => $description,
                'meta_title' => "{$name} Ethnic & Designer Wear | Ravi Exclusive Amritsar",
                'meta_description' => $description,
            ]);
            $this->syncImage($parent, 'image', "/assets/images/categories/{$parentSlug}.jpg", $name);

            foreach (array_keys($children) as $childPosition => $childSlug) {
                $profile = $children[$childSlug];

                $child = $this->saveCategory("{$parentSlug}-{$childSlug}", $childPosition, $parent->id, [
                    'name' => $profile['name'],
                    'description' => $profile['blurb'],
                    'meta_title' => $this->forAudience($profile['name'], $name).' | Ravi Exclusive Amritsar',
                    'meta_description' => Str::limit("{$profile['blurb']} Shop online or visit our store in Katra Jaimal Singh, Amritsar.", self::META_DESCRIPTION_LIMIT),
                ]);

                $subcategories["{$parentSlug}/{$childSlug}"] = [$child, $profile, $name];
            }
        }

        return $subcategories;
    }

    /**
     * @param  array{name: string, description: string, meta_title: string, meta_description: string}  $attributes
     */
    private function saveCategory(string $slug, int $sortOrder, ?int $parentId, array $attributes): Category
    {
        $category = Category::firstOrNew(['slug' => $slug]);

        // parent_id is added by this app, so it is not in the package model's fillable list.
        $category->forceFill([
            ...$attributes,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        return $category;
    }

    /**
     * @param  array{code: int, name: string, category: string, price: int, mrp: ?int, fabric: ?string, sizes: list<string>, image: string, new: bool, bestseller: bool}  $row
     * @param  array{name: string, hsn: string, weight: float, box: array{int, int, int}, blurb: string, care: string}  $profile
     */
    private function seedProduct(array $row, Category $subcategory, array $profile, string $parentName): void
    {
        $slug = $this->uniqueProductSlug($row);
        $baseSku = 'RE-'.str_pad((string) $row['code'], 4, '0', STR_PAD_LEFT);
        [$length, $breadth, $height] = $profile['box'];

        $product = $this->catalog->save(
            Product::firstWhere('slug', $slug) ?? new Product,
            [
                'category_id' => $subcategory->id,
                'name' => $row['name'],
                'slug' => $slug,
                'brand' => 'Ravi Exclusive',
                'sku' => $baseSku,
                'hsn_code' => $profile['hsn'],
                'short_description' => $this->shortDescription($row),
                'description' => $this->description($row, $profile),
                'price' => $row['price'],
                'compare_price' => $row['mrp'],
                'stock' => self::STOCK_PER_VARIANT,
                'is_featured' => $row['bestseller'],
                'is_active' => true,
                'weight' => $profile['weight'],
                'length' => $length,
                'breadth' => $breadth,
                'height' => $height,
                'meta_title' => "{$row['name']} | ".$this->forAudience($profile['name'], $parentName).' | Ravi Exclusive',
                'meta_description' => Str::limit(
                    'Shop the '.$row['name'].($row['fabric'] ? " in {$row['fabric']}" : '')
                    ." from Ravi Exclusive, Katra Jaimal Singh, Amritsar. {$profile['blurb']}",
                    self::META_DESCRIPTION_LIMIT,
                ),
            ],
            $row['sizes'] === [] ? [] : [['name' => 'Size', 'values' => $row['sizes']]],
            array_map(fn (string $size, int $index): array => [
                'sku' => "{$baseSku}-{$size}",
                'name' => $row['name'],
                'option_label' => $size,
                'option_values' => ['Size' => $size],
                'price' => $row['price'],
                'compare_price' => $row['mrp'],
                'stock' => self::STOCK_PER_VARIANT,
                'is_default' => $index === 0,
            ], $row['sizes'], array_keys($row['sizes'])),
            defaultVariantLabel: 'Free Size',
        );

        $product->forceFill([
            'created_at' => $row['new'] ? now()->subWeek() : now()->subMonths(2),
        ])->saveQuietly();

        // Every product in a subcategory shares its slug image; the row's own photo is the fallback.
        $image = $this->slugImage($subcategory->slug) ?? $row['image'];

        $this->syncImage($product, 'gallery', '/assets/images/'.$image, $row['name']);
    }

    /**
     * The client's photo for a category, named after its slug, relative to public/assets/images.
     */
    private function slugImage(string $categorySlug): ?string
    {
        $image = "categories/{$categorySlug}.webp";

        return file_exists(public_path('assets/images/'.$image)) ? $image : null;
    }

    /**
     * @param  array{fabric: ?string}  $row
     */
    private function shortDescription(array $row): ?string
    {
        if (! $row['fabric']) {
            return null;
        }

        return "Fabric: {$row['fabric']}";
    }

    /**
     * Plain text: the admin edits descriptions in a textarea.
     *
     * @param  array{name: string, fabric: ?string, sizes: list<string>}  $row
     * @param  array{name: string, blurb: string, care: string}  $profile
     */
    private function description(array $row, array $profile): string
    {
        $intro = $row['fabric']
            ? "{$row['name']} in {$row['fabric']}, from our {$profile['name']} collection. {$profile['blurb']}"
            : "{$row['name']}, from our {$profile['name']} collection. {$profile['blurb']}";

        $details = array_filter([
            $this->shortDescription($row),
            'Sizes: '.($row['sizes'] === [] ? 'Free size' : implode(', ', $row['sizes'])),
            "Care: {$profile['care']}",
        ]);

        return implode("\n\n", [
            $intro,
            implode("\n", $details),
            'Need help with sizing or alterations? WhatsApp us on +91-814-814-62-62 or visit our store at 22, Katra Jaimal Singh, Amritsar.',
        ]);
    }

    /** "Sherwani for Boys". */
    private function forAudience(string $subcategoryName, string $parentName): string
    {
        return "{$subcategoryName} for {$parentName}";
    }

    /**
     * A few products share a name (two "Chikankari Kurta Pyjama"s), so later ones get their code appended.
     *
     * @param  array{code: int, name: string}  $row
     */
    private function uniqueProductSlug(array $row): string
    {
        $slug = Str::slug($row['name']);

        if (isset($this->usedProductSlugs[$slug])) {
            $slug .= '-'.$row['code'];
        }

        $this->usedProductSlugs[$slug] = true;

        return $slug;
    }

    private function syncImage(Category|Product $owner, string $collection, string $path, string $alt): void
    {
        $owner->load('images');

        if ($owner->imagesIn($collection)->pluck('path')->all() === [$path]) {
            return;
        }

        $this->images->clear($owner, $collection);
        $this->images->attach($owner, $collection, $path, ['alt' => $alt, 'is_primary' => true, 'sort_order' => 0]);
    }

    /**
     * Same order as the storefront's main navigation. Each subcategory carries the
     * details its products share: GST HSN code, packed weight (kg) and box size
     * (L × B × H cm) for courier rates, a line of copy and care instructions.
     *
     * @return array<string, array{name: string, description: string, children: array<string, array{name: string, hsn: string, weight: float, box: array{int, int, int}, blurb: string, care: string}>}>
     */
    private function categories(): array
    {
        $dryClean = 'Dry clean only. Store folded in a muslin cloth, away from damp.';
        $gentleWash = 'Gentle hand wash in cold water or dry clean. Dry in shade and iron on reverse.';

        return [
            'girls' => [
                'name' => 'Girls',
                'description' => 'Lehengas, sharara and palazzo suits, Patiala salwar suits, frocks and gowns for girls aged 1 to 14, from their first festive outfit to the big family wedding.',
                'children' => [
                    'lehenga' => ['name' => 'Lehenga', 'hsn' => '6204', 'weight' => 1.0, 'box' => [35, 28, 10], 'blurb' => 'Lehenga cholis for girls with dupattas, sized to grow into.', 'care' => $dryClean],
                    'sharara-palazzo' => ['name' => 'Sharara / Palazzo', 'hsn' => '6204', 'weight' => 0.6, 'box' => [30, 25, 6], 'blurb' => 'Sharara, gharara and palazzo suit sets in mini sizes.', 'care' => $gentleWash],
                    'patiala-farshi-salwar' => ['name' => 'Patiala Salwar Suits / Farshi Salwar', 'hsn' => '6204', 'weight' => 0.5, 'box' => [30, 25, 5], 'blurb' => 'Patiala and farshi salwar suits for girls, made for long festive days.', 'care' => $gentleWash],
                    'frocks-gowns' => ['name' => 'Frocks & Gowns', 'hsn' => '6204', 'weight' => 0.8, 'box' => [35, 28, 8], 'blurb' => 'Tiered frocks and floor-length gowns with soft inner linings, perfect for sangeet and reception nights.', 'care' => $dryClean],
                ],
            ],
            'boys' => [
                'name' => 'Boys',
                'description' => 'Sherwanis, indo-westerns, coat suits, jodhpuris, kurta pajamas and waistcoat sets for boys aged 1 to 14, so the little ones match the baraat.',
                'children' => [
                    'sherwani' => ['name' => 'Sherwani', 'hsn' => '6203', 'weight' => 1.0, 'box' => [35, 28, 8], 'blurb' => 'Mini sherwanis that match the groom, with soft linings so they stay comfortable.', 'care' => $dryClean],
                    'indo-westerns' => ['name' => 'Indo-Westerns', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 8], 'blurb' => 'Indo-western sets for boys that mix a festive silhouette with modern tailoring.', 'care' => $dryClean],
                    'coat-suits-tuxedos' => ['name' => 'Coat Suits / Tuxedos', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 8], 'blurb' => 'Smart coat suits and tuxedos for boys, for receptions and cocktail evenings.', 'care' => $dryClean],
                    'jodhpuri' => ['name' => 'Jodhpuri', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 8], 'blurb' => 'Bandhgala jodhpuri suits for boys, for receptions and formal functions.', 'care' => $dryClean],
                    'kurta-pajama' => ['name' => 'Kurta Pajama', 'hsn' => '6203', 'weight' => 0.5, 'box' => [30, 25, 5], 'blurb' => 'Comfortable kurta pajama and pathani sets for boys, easy to wear through a long day of functions.', 'care' => $gentleWash],
                    'waistcoat-sets' => ['name' => 'Waistcoat Sets', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 8], 'blurb' => 'Waistcoat and kurta sets so boys look their best at every function.', 'care' => $dryClean],
                ],
            ],
            'men' => [
                'name' => 'Men',
                'description' => 'Kurta pajamas, waistcoat sets and indo-westerns for the groom, the baraat and every festive occasion in between.',
                'children' => [
                    'kurta-pajamas' => ['name' => 'Kurta Pajamas', 'hsn' => '6203', 'weight' => 0.8, 'box' => [35, 28, 6], 'blurb' => 'Kurta pajama and pathani sets for weddings, gurpurab and festive gatherings.', 'care' => $gentleWash],
                    'waistcoat-sets' => ['name' => 'Waistcoat Sets', 'hsn' => '6203', 'weight' => 0.6, 'box' => [35, 28, 5], 'blurb' => 'Waistcoats and Nehru jackets to dress up any kurta pajama.', 'care' => $dryClean],
                    'indo-westerns' => ['name' => 'Indo-Westerns', 'hsn' => '6203', 'weight' => 1.6, 'box' => [40, 32, 8], 'blurb' => 'Indo-westerns, sherwanis and jodhpuris that blend traditional silhouettes with modern tailoring.', 'care' => $dryClean],
                ],
            ],
            'women' => [
                'name' => 'Women',
                'description' => 'Ethnic co-ord sets for women: hand-picked occasion wear from the heart of Amritsar.',
                'children' => [
                    'co-ord-sets' => ['name' => 'Co-ord Sets', 'hsn' => '6204', 'weight' => 0.7, 'box' => [35, 28, 5], 'blurb' => 'Matching ethnic co-ord sets for an easy, put-together festive look.', 'care' => $gentleWash],
                ],
            ],
        ];
    }

    /**
     * Codes match the storefront's "RE-0035" product codes. The home page's own
     * cards reused codes 3, 13, 14, 22 and 27 for different products, so those
     * five are seeded as 95–99. Codes 100–129 fill the kids' subcategories the
     * template left empty. Gaps are products whose subcategory was retired.
     * A row's image is only used while its subcategory has no slug image.
     *
     * @return list<array{code: int, name: string, category: string, price: int, mrp: ?int, fabric: ?string, sizes: list<string>, image: string, new: bool, bestseller: bool}>
     */
    private function products(): array
    {
        return [
            ['code' => 31, 'name' => 'Festive Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 19999, 'mrp' => 23549, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-1.jpg', 'new' => false, 'bestseller' => true],
            ['code' => 32, 'name' => 'Chikankari Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 22549, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 33, 'name' => 'Royal Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 20999, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 34, 'name' => 'Zari Embroidered Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 3549, 'mrp' => 3949, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 35, 'name' => 'Festive Sherwani', 'category' => 'men/indo-westerns', 'price' => 5399, 'mrp' => 7199, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 36, 'name' => 'Pure Silk Sherwani', 'category' => 'men/indo-westerns', 'price' => 21999, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 37, 'name' => 'Heritage Sherwani', 'category' => 'men/indo-westerns', 'price' => 26099, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 38, 'name' => 'Zari Embroidered Sherwani', 'category' => 'men/indo-westerns', 'price' => 7749, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 39, 'name' => 'Sequinned Sherwani', 'category' => 'men/indo-westerns', 'price' => 18349, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-5.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 40, 'name' => 'Velvet Sherwani', 'category' => 'men/indo-westerns', 'price' => 1449, 'mrp' => 1799, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-6.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 41, 'name' => 'Chikankari Kurta Pyjama', 'category' => 'men/kurta-pajamas', 'price' => 14199, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 42, 'name' => 'Sequinned Kurta Pyjama', 'category' => 'men/kurta-pajamas', 'price' => 10949, 'mrp' => 13699, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 43, 'name' => 'Chikankari Kurta Pyjama', 'category' => 'men/kurta-pajamas', 'price' => 12549, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 44, 'name' => 'Classic Kurta Pyjama', 'category' => 'men/kurta-pajamas', 'price' => 8699, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 45, 'name' => 'Zari Embroidered Kurta Pyjama', 'category' => 'men/kurta-pajamas', 'price' => 7099, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 46, 'name' => 'Banarasi Indo-Western', 'category' => 'men/indo-westerns', 'price' => 4799, 'mrp' => 6399, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 47, 'name' => 'Gota Patti Indo-Western', 'category' => 'men/indo-westerns', 'price' => 13299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 48, 'name' => 'Regal Indo-Western', 'category' => 'men/indo-westerns', 'price' => 5849, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 49, 'name' => 'Gota Patti Indo-Western', 'category' => 'men/indo-westerns', 'price' => 5199, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 50, 'name' => 'Regal Nehru Jacket', 'category' => 'men/waistcoat-sets', 'price' => 3249, 'mrp' => 4649, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 51, 'name' => 'Royal Nehru Jacket', 'category' => 'men/waistcoat-sets', 'price' => 12999, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 52, 'name' => 'Sequinned Nehru Jacket', 'category' => 'men/waistcoat-sets', 'price' => 4249, 'mrp' => 5649, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 53, 'name' => 'Chikankari Nehru Jacket', 'category' => 'men/waistcoat-sets', 'price' => 3449, 'mrp' => 4599, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 54, 'name' => 'Chikankari Jodhpuri Suit', 'category' => 'men/indo-westerns', 'price' => 22849, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 55, 'name' => 'Gota Patti Jodhpuri Suit', 'category' => 'men/indo-westerns', 'price' => 8199, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 56, 'name' => 'Gota Patti Jodhpuri Suit', 'category' => 'men/indo-westerns', 'price' => 26249, 'mrp' => 37499, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 57, 'name' => 'Banarasi Jodhpuri Suit', 'category' => 'men/indo-westerns', 'price' => 21549, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 58, 'name' => 'Banarasi Pathani Suit', 'category' => 'men/kurta-pajamas', 'price' => 8449, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 59, 'name' => 'Regal Pathani Suit', 'category' => 'men/kurta-pajamas', 'price' => 4499, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 60, 'name' => 'Classic Pathani Suit', 'category' => 'men/kurta-pajamas', 'price' => 2249, 'mrp' => 2649, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 61, 'name' => 'Heritage Pathani Suit', 'category' => 'men/kurta-pajamas', 'price' => 4649, 'mrp' => 5799, 'fabric' => 'Cotton Silk', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 98, 'name' => 'Indo-Western cum Nawabi', 'category' => 'men/indo-westerns', 'price' => 17649, 'mrp' => null, 'fabric' => null, 'sizes' => self::MENS_SIZES, 'image' => 'product4.jpg', 'new' => true, 'bestseller' => true],
            ['code' => 93, 'name' => 'Rosette Tiered Party Frock', 'category' => 'girls/frocks-gowns', 'price' => 2749, 'mrp' => 3949, 'fabric' => 'Satin & Lace', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => false, 'bestseller' => true],
            ['code' => 94, 'name' => 'Ivory Waistcoat Party Set', 'category' => 'boys/waistcoat-sets', 'price' => 14949, 'mrp' => 19949, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => true],
            // The template has no Boys or Girls products, so each empty kids' subcategory gets
            // three, reusing the template's kids' photos until real product photos exist.
            ['code' => 100, 'name' => 'Ivory Embroidered Kurta Set', 'category' => 'boys/kurta-pajama', 'price' => 2499, 'mrp' => 2999, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 101, 'name' => 'Mustard Festive Kurta Pyjama', 'category' => 'boys/kurta-pajama', 'price' => 1899, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 102, 'name' => 'Pastel Chikankari Kurta Set', 'category' => 'boys/kurta-pajama', 'price' => 2199, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 103, 'name' => 'Ivory Thread-work Sherwani Set', 'category' => 'boys/sherwani', 'price' => 5499, 'mrp' => 6499, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 104, 'name' => 'Maroon Velvet Mini Sherwani', 'category' => 'boys/sherwani', 'price' => 6299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 105, 'name' => 'Gold Brocade Sherwani Set', 'category' => 'boys/sherwani', 'price' => 5899, 'mrp' => 6999, 'fabric' => 'Brocade', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 106, 'name' => 'Classic White Pathani Suit', 'category' => 'boys/kurta-pajama', 'price' => 1799, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 107, 'name' => 'Navy Pathani Suit', 'category' => 'boys/kurta-pajama', 'price' => 1999, 'mrp' => null, 'fabric' => 'Linen Blend', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 108, 'name' => 'Beige Embroidered Pathani Set', 'category' => 'boys/kurta-pajama', 'price' => 2299, 'mrp' => 2699, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 109, 'name' => 'Ivory Waistcoat with Brooch', 'category' => 'boys/waistcoat-sets', 'price' => 1999, 'mrp' => 2499, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 110, 'name' => 'Embroidered Nehru Jacket Set', 'category' => 'boys/waistcoat-sets', 'price' => 2899, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 111, 'name' => 'Velvet Bandhgala Jacket', 'category' => 'boys/waistcoat-sets', 'price' => 3299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 115, 'name' => 'Rosette Ball Gown', 'category' => 'girls/frocks-gowns', 'price' => 3499, 'mrp' => 4499, 'fabric' => 'Satin & Net', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 116, 'name' => 'Plum Anarkali Gown with Dupatta', 'category' => 'girls/frocks-gowns', 'price' => 3999, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 117, 'name' => 'Blush Ruffle Party Gown', 'category' => 'girls/frocks-gowns', 'price' => 2999, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 118, 'name' => 'Plum Embroidered Lehenga Choli', 'category' => 'girls/lehenga', 'price' => 4499, 'mrp' => 5299, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 119, 'name' => 'Jacket Lehenga Set', 'category' => 'girls/lehenga', 'price' => 4999, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 120, 'name' => 'Mirror-work Mini Lehenga', 'category' => 'girls/lehenga', 'price' => 3799, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 121, 'name' => 'Frock-style Kurta with Palazzo', 'category' => 'girls/patiala-farshi-salwar', 'price' => 1999, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 122, 'name' => 'Gota Patti Kurta Set', 'category' => 'girls/patiala-farshi-salwar', 'price' => 2399, 'mrp' => 2799, 'fabric' => 'Chanderi', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 123, 'name' => 'Pastel Embroidered Kurta Set', 'category' => 'girls/patiala-farshi-salwar', 'price' => 2599, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 124, 'name' => 'Sharara Set with Dupatta', 'category' => 'girls/sharara-palazzo', 'price' => 2899, 'mrp' => 3399, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 125, 'name' => 'Embroidered Palazzo Set', 'category' => 'girls/sharara-palazzo', 'price' => 2499, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 126, 'name' => 'Gharara Suit Set', 'category' => 'girls/sharara-palazzo', 'price' => 3199, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
        ];
    }
}
