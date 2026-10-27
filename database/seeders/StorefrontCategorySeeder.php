<?php

namespace Database\Seeders;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Image;
use Carepeerinfotech\Ecommerce\Services\Media\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontCategorySeeder extends Seeder
{
    private const CATEGORY_IMAGE_PATH = '/assets/images/categories/';

    private const META_DESCRIPTION_LIMIT = 157;

    public function __construct(private ImageService $images) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ($this->categories() as $parentPosition => $parentDefinition) {
                $parentSlug = $parentDefinition['slug'];
                $parent = $this->saveCategory($parentSlug, $parentPosition, null, [
                    'name' => $parentDefinition['name'],
                    'description' => $parentDefinition['description'],
                    'meta_title' => "{$parentDefinition['name']} Ethnic & Designer Wear | Ravi Exclusive Amritsar",
                    'meta_description' => $parentDefinition['description'],
                ]);
                $this->syncCategoryImage($parent, self::CATEGORY_IMAGE_PATH.$parentSlug.'.jpg');

                foreach ($parentDefinition['children'] as $childPosition => $child) {
                    $category = $this->saveCategory($child['slug'], $childPosition, $parent, [
                        'name' => $child['name'],
                        'description' => $child['description'],
                        'meta_title' => "{$child['name']} for {$parentDefinition['name']} | Ravi Exclusive Amritsar",
                        'meta_description' => Str::limit(
                            "{$child['description']} Shop online or visit our store in Katra Jaimal Singh, Amritsar.",
                            self::META_DESCRIPTION_LIMIT,
                        ),
                    ]);
                    $this->syncCategoryImage($category, self::CATEGORY_IMAGE_PATH.$child['slug'].'.webp');
                }
            }
        });
    }

    /**
     * @param  array{name: string, description: string, meta_title: string, meta_description: string}  $attributes
     */
    private function saveCategory(string $slug, int $sortOrder, ?Category $parent, array $attributes): Category
    {
        $category = Category::firstOrNew(['slug' => $slug]);
        $category->fill([
            ...$attributes,
            'parent_id' => $parent?->id,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        return $category;
    }

    private function syncCategoryImage(Category $category, string $path): void
    {
        $images = $category->load('images')->imagesIn('image');
        $current = $images->first();

        if ($images->count() === 1 && $current instanceof Image
            && $current->path === $path
            && $current->alt === $category->name
            && $current->is_primary) {
            return;
        }

        $this->images->clear($category, 'image');
        $this->images->attach($category, 'image', $path, [
            'alt' => $category->name,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * Category copy and the hierarchy come from the 17 Oct 2026 database backup.
     *
     * @return list<array{slug: string, name: string, description: string, children: list<array{slug: string, name: string, description: string}>}>
     */
    private function categories(): array
    {
        return [
            [
                'slug' => 'girls',
                'name' => 'Girls',
                'description' => 'Lehengas, sharara and palazzo suits, Patiala salwar suits, frocks and gowns for girls aged 1 to 14, from their first festive outfit to the big family wedding.',
                'children' => [
                    ['slug' => 'girls-lehenga', 'name' => 'Lehenga', 'description' => 'Lehenga cholis for girls with dupattas, sized to grow into.'],
                    ['slug' => 'girls-sharara-palazzo', 'name' => 'Sharara / Palazzo', 'description' => 'Sharara, gharara and palazzo suit sets in mini sizes.'],
                    ['slug' => 'girls-patiala-farshi-salwar', 'name' => 'Patiala Salwar Suits / Farshi Salwar', 'description' => 'Patiala and farshi salwar suits for girls, made for long festive days.'],
                    ['slug' => 'girls-frocks-gowns', 'name' => 'Frocks & Gowns', 'description' => 'Tiered frocks and floor-length gowns with soft inner linings, perfect for sangeet and reception nights.'],
                ],
            ],
            [
                'slug' => 'boys',
                'name' => 'Boys',
                'description' => 'Sherwanis, indo-westerns, coat suits, jodhpuris, kurta pajamas and waistcoat sets for boys aged 1 to 14, so the little ones match the baraat.',
                'children' => [
                    ['slug' => 'boys-sherwani', 'name' => 'Sherwani', 'description' => 'Mini sherwanis that match the groom, with soft linings so they stay comfortable.'],
                    ['slug' => 'boys-indo-westerns', 'name' => 'Indo-Westerns', 'description' => 'Indo-western sets for boys that mix a festive silhouette with modern tailoring.'],
                    ['slug' => 'boys-coat-suits-tuxedos', 'name' => 'Coat Suits / Tuxedos', 'description' => 'Smart coat suits and tuxedos for boys, for receptions and cocktail evenings.'],
                    ['slug' => 'boys-jodhpuri', 'name' => 'Jodhpuri', 'description' => 'Bandhgala jodhpuri suits for boys, for receptions and formal functions.'],
                    ['slug' => 'boys-kurta-pajama', 'name' => 'Kurta Pajama', 'description' => 'Comfortable kurta pajama and pathani sets for boys, easy to wear through a long day of functions.'],
                    ['slug' => 'boys-waistcoat-sets', 'name' => 'Waistcoat Sets', 'description' => 'Waistcoat and kurta sets so boys look their best at every function.'],
                ],
            ],
            [
                'slug' => 'men',
                'name' => 'Men',
                'description' => 'Kurta pajamas, waistcoat sets and indo-westerns for the groom, the baraat and every festive occasion in between.',
                'children' => [
                    ['slug' => 'men-kurta-pajamas', 'name' => 'Kurta Pajamas', 'description' => 'Kurta pajama and pathani sets for weddings, gurpurab and festive gatherings.'],
                    ['slug' => 'men-waistcoat-sets', 'name' => 'Waistcoat Sets', 'description' => 'Waistcoats and Nehru jackets to dress up any kurta pajama.'],
                    ['slug' => 'men-indo-westerns', 'name' => 'Indo-Westerns', 'description' => 'Indo-westerns, sherwanis and jodhpuris that blend traditional silhouettes with modern tailoring.'],
                ],
            ],
            [
                'slug' => 'women',
                'name' => 'Women',
                'description' => 'Ethnic co-ord sets for women: hand-picked occasion wear from the heart of Amritsar.',
                'children' => [
                    ['slug' => 'women-co-ord-sets', 'name' => 'Co-ord Sets', 'description' => 'Matching ethnic co-ord sets for an easy, put-together festive look.'],
                ],
            ],
        ];
    }
}
