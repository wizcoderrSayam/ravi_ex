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

    private const FREE_SIZE = [];

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

            // Each subcategory shows its first product's photo.
            foreach (collect($this->products())->unique('category') as $product) {
                [$subcategory, $profile] = $subcategories[$product['category']];
                $this->syncImage($subcategory, 'image', '/assets/images/'.$product['image'], $profile['name']);
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
                'short_description' => $this->shortDescription($row, $parentName),
                'description' => $this->description($row, $profile, $parentName),
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

        $this->syncImage($product, 'gallery', '/assets/images/'.$row['image'], $row['name']);
    }

    /**
     * @param  array{fabric: ?string}  $row
     */
    private function shortDescription(array $row, string $parentName): ?string
    {
        if (! $row['fabric']) {
            return null;
        }

        // The demo lists a "fabric" for accessories too, which reads better as a material.
        return ($parentName === 'Accessories' ? 'Material' : 'Fabric').": {$row['fabric']}";
    }

    /**
     * Plain text: the admin edits descriptions in a textarea.
     *
     * @param  array{name: string, fabric: ?string, sizes: list<string>}  $row
     * @param  array{name: string, blurb: string, care: string}  $profile
     */
    private function description(array $row, array $profile, string $parentName): string
    {
        $intro = $row['fabric']
            ? "{$row['name']} in {$row['fabric']}, from our {$profile['name']} collection. {$profile['blurb']}"
            : "{$row['name']}, from our {$profile['name']} collection. {$profile['blurb']}";

        $details = array_filter([
            $this->shortDescription($row, $parentName),
            'Sizes: '.($row['sizes'] === [] ? 'Free size' : implode(', ', $row['sizes'])),
            "Care: {$profile['care']}",
        ]);

        return implode("\n\n", [
            $intro,
            implode("\n", $details),
            'Need help with sizing or alterations? WhatsApp us on +91-814-814-62-62 or visit our store at 22, Katra Jaimal Singh, Amritsar.',
        ]);
    }

    /** "Sherwanis for Men", but just "Jewellery" under Accessories. */
    private function forAudience(string $subcategoryName, string $parentName): string
    {
        return $parentName === 'Accessories' ? $subcategoryName : "{$subcategoryName} for {$parentName}";
    }

    /**
     * A few products share a name (two "Regal Safa"s), so later ones get their code appended.
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
            'boys' => [
                'name' => 'Boys',
                'description' => 'Festive and wedding wear for boys aged 1 to 14: kurta sets, sherwanis and pathani suits so the little ones match the baraat.',
                'children' => [
                    'kurta-sets' => ['name' => 'Kurta Sets', 'hsn' => '6203', 'weight' => 0.5, 'box' => [30, 25, 5], 'blurb' => 'Comfortable kurta sets for boys, easy to wear through a long day of functions.', 'care' => $gentleWash],
                    'sherwanis' => ['name' => 'Sherwanis', 'hsn' => '6203', 'weight' => 1.0, 'box' => [35, 28, 8], 'blurb' => 'Mini sherwanis that match the groom, with soft linings so they stay comfortable.', 'care' => $dryClean],
                    'pathani-suits' => ['name' => 'Pathani Suits', 'hsn' => '6203', 'weight' => 0.5, 'box' => [30, 25, 5], 'blurb' => 'Classic Punjabi pathani suits for boys, smart for gurdwara visits and family occasions.', 'care' => $gentleWash],
                    'wedding-wear' => ['name' => 'Wedding Wear', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 8], 'blurb' => 'Waistcoat sets and festive ensembles so boys look their best at every rasam.', 'care' => $dryClean],
                    'jackets' => ['name' => 'Jackets', 'hsn' => '6203', 'weight' => 0.4, 'box' => [30, 25, 5], 'blurb' => 'Nehru and festive jackets for boys to layer over a kurta.', 'care' => $dryClean],
                    'shirts' => ['name' => 'Shirts', 'hsn' => '6205', 'weight' => 0.3, 'box' => [30, 22, 4], 'blurb' => 'Festive shirts for boys in breathable fabrics.', 'care' => $gentleWash],
                ],
            ],
            'girls' => [
                'name' => 'Girls',
                'description' => 'Party dresses, festive gowns and lehengas for girls aged 1 to 14, made for twirling through every celebration.',
                'children' => [
                    'party-dresses' => ['name' => 'Party Dresses', 'hsn' => '6204', 'weight' => 0.6, 'box' => [35, 28, 8], 'blurb' => 'Tiered frocks and party dresses with soft inner linings for little ones.', 'care' => $dryClean],
                    'festive-gowns' => ['name' => 'Festive Gowns', 'hsn' => '6204', 'weight' => 0.8, 'box' => [35, 28, 8], 'blurb' => 'Floor-length festive gowns for girls, perfect for sangeet and reception nights.', 'care' => $dryClean],
                    'lehengas' => ['name' => 'Lehengas', 'hsn' => '6204', 'weight' => 1.0, 'box' => [35, 28, 10], 'blurb' => 'Lehenga cholis for girls with dupattas, sized to grow into.', 'care' => $dryClean],
                    'kurta-sets' => ['name' => 'Kurta Sets', 'hsn' => '6204', 'weight' => 0.5, 'box' => [30, 25, 5], 'blurb' => 'Easy kurta sets for girls, made for long festive days.', 'care' => $gentleWash],
                    'ethnic-sets' => ['name' => 'Ethnic Sets', 'hsn' => '6204', 'weight' => 0.6, 'box' => [30, 25, 6], 'blurb' => 'Sharara, palazzo and Punjabi suit sets in mini sizes.', 'care' => $gentleWash],
                    'accessories' => ['name' => 'Accessories', 'hsn' => '7117', 'weight' => 0.2, 'box' => [20, 15, 5], 'blurb' => 'Hair accessories, bangles and potlis to finish a girl\'s festive look.', 'care' => 'Keep away from water and perfume. Store in the box provided.'],
                ],
            ],
            'women' => [
                'name' => 'Women',
                'description' => 'Bridal and party lehengas, Punjabi suits, anarkalis, kurtis and sarees: hand-picked occasion wear for women from the heart of Amritsar.',
                'children' => [
                    'bridal-lehengas' => ['name' => 'Bridal Lehengas', 'hsn' => '6204', 'weight' => 3.5, 'box' => [50, 40, 15], 'blurb' => 'Heavily embroidered bridal lehengas with can-can, blouse piece and dupatta, made for the doli and the pheras.', 'care' => $dryClean],
                    'party-lehengas' => ['name' => 'Party Lehengas', 'hsn' => '6204', 'weight' => 2.5, 'box' => [45, 35, 12], 'blurb' => 'Lighter lehengas for mehndi, sangeet and reception nights.', 'care' => $dryClean],
                    'punjabi-suits' => ['name' => 'Punjabi Suits', 'hsn' => '6204', 'weight' => 0.9, 'box' => [35, 28, 6], 'blurb' => 'Phulkari, gota and embroidered Punjabi suits with salwar and dupatta, from shagan to Lohri.', 'care' => $dryClean],
                    'anarkalis' => ['name' => 'Anarkalis', 'hsn' => '6204', 'weight' => 1.2, 'box' => [40, 30, 8], 'blurb' => 'Flared anarkali suits that move beautifully on the dance floor.', 'care' => $dryClean],
                    'kurtis' => ['name' => 'Kurtis', 'hsn' => '6204', 'weight' => 0.4, 'box' => [32, 25, 4], 'blurb' => 'Festive and everyday kurtis to pair with palazzos, salwars or jeans.', 'care' => $gentleWash],
                    'sarees' => ['name' => 'Sarees', 'hsn' => '5407', 'weight' => 0.8, 'box' => [35, 28, 5], 'blurb' => 'Sarees with unstitched blouse pieces, for receptions and family functions.', 'care' => $dryClean],
                    'co-ord-sets' => ['name' => 'Co-ord Sets', 'hsn' => '6204', 'weight' => 0.7, 'box' => [35, 28, 5], 'blurb' => 'Matching ethnic co-ord sets for an easy, put-together festive look.', 'care' => $gentleWash],
                ],
            ],
            'men' => [
                'name' => 'Men',
                'description' => 'Sherwanis, kurta pyjamas, jodhpuri and pathani suits for the groom, the baraat and every festive occasion in between.',
                'children' => [
                    'sherwanis' => ['name' => 'Sherwanis', 'hsn' => '6203', 'weight' => 1.8, 'box' => [40, 32, 10], 'blurb' => 'Groom and baraat sherwanis with churidar, ready to pair with a safa and juttis.', 'care' => $dryClean],
                    'kurta-pyjama' => ['name' => 'Kurta Pyjama', 'hsn' => '6203', 'weight' => 0.8, 'box' => [35, 28, 6], 'blurb' => 'Kurta pyjama sets for weddings, gurpurab and festive gatherings.', 'care' => $gentleWash],
                    'indo-western' => ['name' => 'Indo-Western', 'hsn' => '6203', 'weight' => 1.5, 'box' => [40, 32, 8], 'blurb' => 'Indo-western outfits that blend a sherwani silhouette with modern tailoring, made for receptions and sangeet.', 'care' => $dryClean],
                    'nehru-jackets' => ['name' => 'Nehru Jackets', 'hsn' => '6203', 'weight' => 0.6, 'box' => [35, 28, 5], 'blurb' => 'Nehru jackets to dress up any kurta pyjama.', 'care' => $dryClean],
                    'jodhpuri-suits' => ['name' => 'Jodhpuri Suits', 'hsn' => '6203', 'weight' => 1.6, 'box' => [40, 32, 8], 'blurb' => 'Bandhgala jodhpuri suits with trousers for receptions and formal functions.', 'care' => $dryClean],
                    'pathani-suits' => ['name' => 'Pathani Suits', 'hsn' => '6203', 'weight' => 0.9, 'box' => [35, 28, 6], 'blurb' => 'Classic Punjabi pathani suits, comfortable from morning ceremonies to the evening.', 'care' => $gentleWash],
                    'shirts' => ['name' => 'Shirts', 'hsn' => '6205', 'weight' => 0.4, 'box' => [32, 25, 4], 'blurb' => 'Festive and occasion shirts in breathable fabrics.', 'care' => $gentleWash],
                    'jackets' => ['name' => 'Jackets', 'hsn' => '6203', 'weight' => 0.8, 'box' => [35, 28, 6], 'blurb' => 'Blazers and jackets for receptions and cocktail evenings.', 'care' => $dryClean],
                    'trousers' => ['name' => 'Trousers', 'hsn' => '6203', 'weight' => 0.5, 'box' => [32, 25, 4], 'blurb' => 'Occasion trousers to pair with jackets, kurtas and Nehru jackets.', 'care' => $dryClean],
                ],
            ],
            'accessories' => [
                'name' => 'Accessories',
                'description' => 'Turbans and safas, jewellery, Punjabi juttis, clutches and kalgis: everything to finish a Punjabi wedding look.',
                'children' => [
                    'turbans-and-safas' => ['name' => 'Turbans & Safas', 'hsn' => '6214', 'weight' => 0.5, 'box' => [35, 25, 6], 'blurb' => 'Wedding safas and pagg for the groom and the baraat, in colours to match the sherwani.', 'care' => $dryClean],
                    'jewellery' => ['name' => 'Jewellery', 'hsn' => '7117', 'weight' => 0.4, 'box' => [20, 15, 8], 'blurb' => 'Kundan and polki style sets, maang tikkas and earrings for brides and guests.', 'care' => 'Keep away from water and perfume. Store in the box provided.'],
                    'punjabi-juttis' => ['name' => 'Punjabi Juttis', 'hsn' => '6403', 'weight' => 0.8, 'box' => [32, 20, 12], 'blurb' => 'Handcrafted Punjabi juttis with embroidery, straight from Amritsar.', 'care' => 'Wipe with a dry cloth. Store in the pouch provided, away from moisture.'],
                    'clutches-and-potlis' => ['name' => 'Clutches & Potlis', 'hsn' => '4202', 'weight' => 0.4, 'box' => [25, 18, 8], 'blurb' => 'Embroidered potlis and clutches to carry shagan envelopes in style.', 'care' => 'Spot clean only. Store stuffed with tissue to keep the shape.'],
                    'brooches-and-kalgis' => ['name' => 'Brooches & Kalgis', 'hsn' => '7117', 'weight' => 0.1, 'box' => [12, 10, 5], 'blurb' => 'Kalgis and brooches to finish the groom\'s safa and sherwani.', 'care' => 'Keep away from water and perfume. Store in the box provided.'],
                ],
            ],
        ];
    }

    /**
     * Codes match the storefront's "RE-0035" product codes. The home page's own
     * cards reused codes 3, 13, 14, 22 and 27 for different products, so those
     * five are seeded as 95–99. Codes 100–129 fill the kids' subcategories the
     * template left empty.
     *
     * @return list<array{code: int, name: string, category: string, price: int, mrp: ?int, fabric: ?string, sizes: list<string>, image: string, new: bool, bestseller: bool}>
     */
    private function products(): array
    {
        return [
            ['code' => 1, 'name' => 'Regal Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 14949, 'mrp' => 19949, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 2, 'name' => 'Chikankari Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 26749, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 3, 'name' => 'Pure Silk Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 34399, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 4, 'name' => 'Regal Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 39049, 'mrp' => 48799, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 5, 'name' => 'Gota Patti Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 43899, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 6, 'name' => 'Velvet Bridal Lehenga', 'category' => 'women/bridal-lehengas', 'price' => 8599, 'mrp' => 10749, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-bridal-lehengas-6.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 7, 'name' => 'Chikankari Lehenga', 'category' => 'women/party-lehengas', 'price' => 23949, 'mrp' => 28199, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-party-lehengas-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 8, 'name' => 'Festive Lehenga', 'category' => 'women/party-lehengas', 'price' => 24749, 'mrp' => 29099, 'fabric' => 'Velvet', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-party-lehengas-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 9, 'name' => 'Phulkari Lehenga', 'category' => 'women/party-lehengas', 'price' => 5749, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-party-lehengas-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 10, 'name' => 'Velvet Lehenga', 'category' => 'women/party-lehengas', 'price' => 15099, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-party-lehengas-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 11, 'name' => 'Regal Lehenga', 'category' => 'women/party-lehengas', 'price' => 17749, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-party-lehengas-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 12, 'name' => 'Classic Punjabi Suit', 'category' => 'women/punjabi-suits', 'price' => 11649, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-punjabi-suits-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 13, 'name' => 'Velvet Punjabi Suit', 'category' => 'women/punjabi-suits', 'price' => 11499, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-punjabi-suits-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 14, 'name' => 'Zari Embroidered Punjabi Suit', 'category' => 'women/punjabi-suits', 'price' => 11899, 'mrp' => 13199, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-punjabi-suits-3.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 15, 'name' => 'Festive Punjabi Suit', 'category' => 'women/punjabi-suits', 'price' => 13199, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-punjabi-suits-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 16, 'name' => 'Regal Punjabi Suit', 'category' => 'women/punjabi-suits', 'price' => 24149, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-punjabi-suits-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 17, 'name' => 'Heritage Anarkali', 'category' => 'women/anarkalis', 'price' => 3449, 'mrp' => 4599, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-anarkalis-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 18, 'name' => 'Sequinned Anarkali', 'category' => 'women/anarkalis', 'price' => 7599, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-anarkalis-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 19, 'name' => 'Zari Embroidered Anarkali', 'category' => 'women/anarkalis', 'price' => 13999, 'mrp' => 19999, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-anarkalis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 20, 'name' => 'Regal Anarkali', 'category' => 'women/anarkalis', 'price' => 6299, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-anarkalis-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 21, 'name' => 'Heritage Kurti', 'category' => 'women/kurtis', 'price' => 6849, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-kurtis-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 22, 'name' => 'Sequinned Kurti', 'category' => 'women/kurtis', 'price' => 14249, 'mrp' => 15849, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-kurtis-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 23, 'name' => 'Regal Kurti', 'category' => 'women/kurtis', 'price' => 2449, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-kurtis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 24, 'name' => 'Chikankari Kurti', 'category' => 'women/kurtis', 'price' => 12249, 'mrp' => 16349, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-kurtis-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 25, 'name' => 'Pure Silk Kurti', 'category' => 'women/kurtis', 'price' => 24199, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-kurtis-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 26, 'name' => 'Velvet Saree', 'category' => 'women/sarees', 'price' => 20049, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-sarees-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 27, 'name' => 'Classic Saree', 'category' => 'women/sarees', 'price' => 17649, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-sarees-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 28, 'name' => 'Zari Embroidered Saree', 'category' => 'women/sarees', 'price' => 14199, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-sarees-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 29, 'name' => 'Festive Saree', 'category' => 'women/sarees', 'price' => 12899, 'mrp' => 17199, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-sarees-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 30, 'name' => 'Chikankari Saree', 'category' => 'women/sarees', 'price' => 10749, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-sarees-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 31, 'name' => 'Festive Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 19999, 'mrp' => 23549, 'fabric' => 'Organza', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 32, 'name' => 'Chikankari Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 22549, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 33, 'name' => 'Royal Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 20999, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 34, 'name' => 'Zari Embroidered Co-ord Set', 'category' => 'women/co-ord-sets', 'price' => 3549, 'mrp' => 3949, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'products/women-co-ord-sets-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 35, 'name' => 'Festive Sherwani', 'category' => 'men/sherwanis', 'price' => 5399, 'mrp' => 7199, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 36, 'name' => 'Pure Silk Sherwani', 'category' => 'men/sherwanis', 'price' => 21999, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 37, 'name' => 'Heritage Sherwani', 'category' => 'men/sherwanis', 'price' => 26099, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 38, 'name' => 'Zari Embroidered Sherwani', 'category' => 'men/sherwanis', 'price' => 7749, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 39, 'name' => 'Sequinned Sherwani', 'category' => 'men/sherwanis', 'price' => 18349, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-5.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 40, 'name' => 'Velvet Sherwani', 'category' => 'men/sherwanis', 'price' => 1449, 'mrp' => 1799, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-sherwanis-6.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 41, 'name' => 'Chikankari Kurta Pyjama', 'category' => 'men/kurta-pyjama', 'price' => 14199, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 42, 'name' => 'Sequinned Kurta Pyjama', 'category' => 'men/kurta-pyjama', 'price' => 10949, 'mrp' => 13699, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 43, 'name' => 'Chikankari Kurta Pyjama', 'category' => 'men/kurta-pyjama', 'price' => 12549, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 44, 'name' => 'Classic Kurta Pyjama', 'category' => 'men/kurta-pyjama', 'price' => 8699, 'mrp' => null, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 45, 'name' => 'Zari Embroidered Kurta Pyjama', 'category' => 'men/kurta-pyjama', 'price' => 7099, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-kurta-pyjama-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 46, 'name' => 'Banarasi Indo-Western', 'category' => 'men/indo-western', 'price' => 4799, 'mrp' => 6399, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 47, 'name' => 'Gota Patti Indo-Western', 'category' => 'men/indo-western', 'price' => 13299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 48, 'name' => 'Regal Indo-Western', 'category' => 'men/indo-western', 'price' => 5849, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 49, 'name' => 'Gota Patti Indo-Western', 'category' => 'men/indo-western', 'price' => 5199, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-indo-western-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 50, 'name' => 'Regal Nehru Jacket', 'category' => 'men/nehru-jackets', 'price' => 3249, 'mrp' => 4649, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 51, 'name' => 'Royal Nehru Jacket', 'category' => 'men/nehru-jackets', 'price' => 12999, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 52, 'name' => 'Sequinned Nehru Jacket', 'category' => 'men/nehru-jackets', 'price' => 4249, 'mrp' => 5649, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 53, 'name' => 'Chikankari Nehru Jacket', 'category' => 'men/nehru-jackets', 'price' => 3449, 'mrp' => 4599, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-nehru-jackets-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 54, 'name' => 'Chikankari Jodhpuri Suit', 'category' => 'men/jodhpuri-suits', 'price' => 22849, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 55, 'name' => 'Gota Patti Jodhpuri Suit', 'category' => 'men/jodhpuri-suits', 'price' => 8199, 'mrp' => null, 'fabric' => 'Silk Blend', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 56, 'name' => 'Gota Patti Jodhpuri Suit', 'category' => 'men/jodhpuri-suits', 'price' => 26249, 'mrp' => 37499, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 57, 'name' => 'Banarasi Jodhpuri Suit', 'category' => 'men/jodhpuri-suits', 'price' => 21549, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jodhpuri-suits-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 58, 'name' => 'Banarasi Pathani Suit', 'category' => 'men/pathani-suits', 'price' => 8449, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 59, 'name' => 'Regal Pathani Suit', 'category' => 'men/pathani-suits', 'price' => 4499, 'mrp' => null, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 60, 'name' => 'Classic Pathani Suit', 'category' => 'men/pathani-suits', 'price' => 2249, 'mrp' => 2649, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 61, 'name' => 'Heritage Pathani Suit', 'category' => 'men/pathani-suits', 'price' => 4649, 'mrp' => 5799, 'fabric' => 'Cotton Silk', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-pathani-suits-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 62, 'name' => 'Mirror-work Shirt', 'category' => 'men/shirts', 'price' => 5149, 'mrp' => 5699, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-shirts-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 63, 'name' => 'Banarasi Shirt', 'category' => 'men/shirts', 'price' => 13599, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-shirts-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 64, 'name' => 'Classic Shirt', 'category' => 'men/shirts', 'price' => 14299, 'mrp' => null, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-shirts-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 65, 'name' => 'Festive Shirt', 'category' => 'men/shirts', 'price' => 2399, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-shirts-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 66, 'name' => 'Olive Denim Jacket', 'category' => 'men/jackets', 'price' => 2499, 'mrp' => 2999, 'fabric' => 'Denim', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-jackets-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 67, 'name' => 'Festive Trouser', 'category' => 'men/trousers', 'price' => 12099, 'mrp' => 13449, 'fabric' => 'Jacquard', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-trousers-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 68, 'name' => 'Royal Trouser', 'category' => 'men/trousers', 'price' => 14249, 'mrp' => 17799, 'fabric' => 'Brocade', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-trousers-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 69, 'name' => 'Classic Trouser', 'category' => 'men/trousers', 'price' => 4749, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-trousers-3.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 70, 'name' => 'Chikankari Trouser', 'category' => 'men/trousers', 'price' => 14049, 'mrp' => 20049, 'fabric' => 'Linen', 'sizes' => self::MENS_SIZES, 'image' => 'products/men-trousers-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 71, 'name' => 'Velvet Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 1149, 'mrp' => null, 'fabric' => 'Polki', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 72, 'name' => 'Regal Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 3099, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 73, 'name' => 'Heritage Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 2749, 'mrp' => 3949, 'fabric' => 'Kundan', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 74, 'name' => 'Banarasi Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 1899, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 75, 'name' => 'Festive Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 399, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 76, 'name' => 'Regal Safa', 'category' => 'accessories/turbans-and-safas', 'price' => 3049, 'mrp' => 3799, 'fabric' => 'Velvet', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-turbans-and-safas-6.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 77, 'name' => 'Chikankari Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 2049, 'mrp' => 2549, 'fabric' => 'Leather', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 78, 'name' => 'Velvet Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 1149, 'mrp' => null, 'fabric' => 'Pashmina', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 79, 'name' => 'Velvet Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 2349, 'mrp' => null, 'fabric' => 'Pashmina', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 80, 'name' => 'Festive Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 699, 'mrp' => 949, 'fabric' => 'Polki', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 81, 'name' => 'Velvet Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 1649, 'mrp' => 1849, 'fabric' => 'Kundan', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-5.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 82, 'name' => 'Zari Embroidered Jewellery Set', 'category' => 'accessories/jewellery', 'price' => 2449, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-6.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 83, 'name' => 'Heritage Punjabi Jutti', 'category' => 'accessories/punjabi-juttis', 'price' => 1349, 'mrp' => 1599, 'fabric' => 'Velvet', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-punjabi-juttis-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 84, 'name' => 'Gota Patti Punjabi Jutti', 'category' => 'accessories/punjabi-juttis', 'price' => 2849, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-punjabi-juttis-2.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 85, 'name' => 'Zari Embroidered Punjabi Jutti', 'category' => 'accessories/punjabi-juttis', 'price' => 2449, 'mrp' => null, 'fabric' => 'Polki', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-punjabi-juttis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 86, 'name' => 'Velvet Punjabi Jutti', 'category' => 'accessories/punjabi-juttis', 'price' => 3699, 'mrp' => 4099, 'fabric' => 'Leather', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-punjabi-juttis-4.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 87, 'name' => 'Chikankari Potli', 'category' => 'accessories/clutches-and-potlis', 'price' => 2149, 'mrp' => 2699, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-clutches-and-potlis-1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 88, 'name' => 'Sequinned Potli', 'category' => 'accessories/clutches-and-potlis', 'price' => 2299, 'mrp' => null, 'fabric' => 'Leather', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-clutches-and-potlis-2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 89, 'name' => 'Phulkari Potli', 'category' => 'accessories/clutches-and-potlis', 'price' => 2399, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-clutches-and-potlis-3.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 90, 'name' => 'Classic Potli', 'category' => 'accessories/clutches-and-potlis', 'price' => 3199, 'mrp' => null, 'fabric' => 'Pashmina', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-clutches-and-potlis-4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 91, 'name' => 'Phulkari Kalgi', 'category' => 'accessories/brooches-and-kalgis', 'price' => 1799, 'mrp' => 2549, 'fabric' => 'Pashmina', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-brooches-and-kalgis-1.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 95, 'name' => 'Farshi Salwar Set', 'category' => 'women/punjabi-suits', 'price' => 11499, 'mrp' => null, 'fabric' => null, 'sizes' => self::WOMENS_SIZES, 'image' => 'product1.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 96, 'name' => 'Sharara Suit', 'category' => 'women/punjabi-suits', 'price' => 11899, 'mrp' => 13199, 'fabric' => null, 'sizes' => self::WOMENS_SIZES, 'image' => 'product2.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 97, 'name' => 'Flowy Kaftan-style Kurta', 'category' => 'women/kurtis', 'price' => 14249, 'mrp' => 15849, 'fabric' => null, 'sizes' => self::WOMENS_SIZES, 'image' => 'product3.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 98, 'name' => 'Indo-Western cum Nawabi', 'category' => 'men/indo-western', 'price' => 17649, 'mrp' => null, 'fabric' => null, 'sizes' => self::MENS_SIZES, 'image' => 'product4.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 99, 'name' => 'Women Lehnga Set', 'category' => 'women/bridal-lehengas', 'price' => 34399, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::WOMENS_SIZES, 'image' => 'product5.jpg', 'new' => false, 'bestseller' => true],
            ['code' => 92, 'name' => 'Ivory Textured Linen Blazer', 'category' => 'men/jackets', 'price' => 26099, 'mrp' => null, 'fabric' => 'Linen Blend', 'sizes' => self::MENS_SIZES, 'image' => 'product6.jpg', 'new' => false, 'bestseller' => true],
            ['code' => 93, 'name' => 'Rosette Tiered Party Frock', 'category' => 'girls/party-dresses', 'price' => 2749, 'mrp' => 3949, 'fabric' => 'Satin & Lace', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => false, 'bestseller' => true],
            ['code' => 94, 'name' => 'Ivory Waistcoat Party Set', 'category' => 'boys/wedding-wear', 'price' => 14949, 'mrp' => 19949, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => true],
            // The template has no Boys or Girls products, so each empty kids' subcategory gets
            // three, reusing the template's kids' photos until real product photos exist.
            ['code' => 100, 'name' => 'Ivory Embroidered Kurta Set', 'category' => 'boys/kurta-sets', 'price' => 2499, 'mrp' => 2999, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 101, 'name' => 'Mustard Festive Kurta Pyjama', 'category' => 'boys/kurta-sets', 'price' => 1899, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 102, 'name' => 'Pastel Chikankari Kurta Set', 'category' => 'boys/kurta-sets', 'price' => 2199, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 103, 'name' => 'Ivory Thread-work Sherwani Set', 'category' => 'boys/sherwanis', 'price' => 5499, 'mrp' => 6499, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 104, 'name' => 'Maroon Velvet Mini Sherwani', 'category' => 'boys/sherwanis', 'price' => 6299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 105, 'name' => 'Gold Brocade Sherwani Set', 'category' => 'boys/sherwanis', 'price' => 5899, 'mrp' => 6999, 'fabric' => 'Brocade', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 106, 'name' => 'Classic White Pathani Suit', 'category' => 'boys/pathani-suits', 'price' => 1799, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 107, 'name' => 'Navy Pathani Suit', 'category' => 'boys/pathani-suits', 'price' => 1999, 'mrp' => null, 'fabric' => 'Linen Blend', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 108, 'name' => 'Beige Embroidered Pathani Set', 'category' => 'boys/pathani-suits', 'price' => 2299, 'mrp' => 2699, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 109, 'name' => 'Ivory Waistcoat with Brooch', 'category' => 'boys/jackets', 'price' => 1999, 'mrp' => 2499, 'fabric' => 'Jacquard', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 110, 'name' => 'Embroidered Nehru Jacket Set', 'category' => 'boys/jackets', 'price' => 2899, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/boys.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 111, 'name' => 'Velvet Bandhgala Jacket', 'category' => 'boys/jackets', 'price' => 3299, 'mrp' => null, 'fabric' => 'Velvet', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 112, 'name' => 'Black Party Shirt', 'category' => 'boys/shirts', 'price' => 1299, 'mrp' => null, 'fabric' => 'Cotton Satin', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 113, 'name' => 'Mandarin Collar Festive Shirt', 'category' => 'boys/shirts', 'price' => 1199, 'mrp' => null, 'fabric' => 'Linen Blend', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 114, 'name' => 'Silk Blend Occasion Shirt', 'category' => 'boys/shirts', 'price' => 1399, 'mrp' => 1699, 'fabric' => 'Silk Blend', 'sizes' => self::KIDS_SIZES, 'image' => 'product8.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 115, 'name' => 'Rosette Ball Gown', 'category' => 'girls/festive-gowns', 'price' => 3499, 'mrp' => 4499, 'fabric' => 'Satin & Net', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 116, 'name' => 'Plum Anarkali Gown with Dupatta', 'category' => 'girls/festive-gowns', 'price' => 3999, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 117, 'name' => 'Blush Ruffle Party Gown', 'category' => 'girls/festive-gowns', 'price' => 2999, 'mrp' => null, 'fabric' => 'Net', 'sizes' => self::KIDS_SIZES, 'image' => 'product7.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 118, 'name' => 'Plum Embroidered Lehenga Choli', 'category' => 'girls/lehengas', 'price' => 4499, 'mrp' => 5299, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => true, 'bestseller' => false],
            ['code' => 119, 'name' => 'Jacket Lehenga Set', 'category' => 'girls/lehengas', 'price' => 4999, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 120, 'name' => 'Mirror-work Mini Lehenga', 'category' => 'girls/lehengas', 'price' => 3799, 'mrp' => null, 'fabric' => 'Cotton Silk', 'sizes' => self::KIDS_SIZES, 'image' => 'categories/girls.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 121, 'name' => 'Frock-style Kurta with Palazzo', 'category' => 'girls/kurta-sets', 'price' => 1999, 'mrp' => null, 'fabric' => 'Cotton', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 122, 'name' => 'Gota Patti Kurta Set', 'category' => 'girls/kurta-sets', 'price' => 2399, 'mrp' => 2799, 'fabric' => 'Chanderi', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 123, 'name' => 'Pastel Embroidered Kurta Set', 'category' => 'girls/kurta-sets', 'price' => 2599, 'mrp' => null, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 124, 'name' => 'Sharara Set with Dupatta', 'category' => 'girls/ethnic-sets', 'price' => 2899, 'mrp' => 3399, 'fabric' => 'Georgette', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 125, 'name' => 'Embroidered Palazzo Set', 'category' => 'girls/ethnic-sets', 'price' => 2499, 'mrp' => null, 'fabric' => 'Chanderi', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-a.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 126, 'name' => 'Gharara Suit Set', 'category' => 'girls/ethnic-sets', 'price' => 3199, 'mrp' => null, 'fabric' => 'Organza', 'sizes' => self::KIDS_SIZES, 'image' => 'hero-b.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 127, 'name' => 'Mini Embroidered Potli', 'category' => 'girls/accessories', 'price' => 699, 'mrp' => null, 'fabric' => 'Silk', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-clutches-and-potlis-3.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 128, 'name' => 'Kids\' Jhumki Earrings', 'category' => 'girls/accessories', 'price' => 899, 'mrp' => null, 'fabric' => 'Kundan', 'sizes' => self::FREE_SIZE, 'image' => 'products/accessories-jewellery-6.jpg', 'new' => false, 'bestseller' => false],
            ['code' => 129, 'name' => 'Rosette Hair Clip Set', 'category' => 'girls/accessories', 'price' => 499, 'mrp' => null, 'fabric' => 'Satin', 'sizes' => self::FREE_SIZE, 'image' => 'product7.jpg', 'new' => false, 'bestseller' => false],
        ];
    }
}
