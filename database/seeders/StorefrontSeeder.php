<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Category;
use Peer\CMS\Models\Form;
use Peer\CMS\Models\FormFieldRule;
use Peer\CMS\Models\Layout;
use Peer\CMS\Models\Media;
use Peer\CMS\Models\Menu;
use Peer\CMS\Models\Page;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\ProductOffer;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;

/**
 * A demo store: categories, products with SVG pictures in the media library, a nested menu,
 * the "contact" form, and the storefront itself, which is CMS content: one layout, reusable
 * components (their HTML and JS live in database/seeders/storefront) and pages that place them.
 * Everything can be edited afterwards in the admin panel and in GrapesJS.
 */
class StorefrontSeeder extends Seeder
{
    /**
     * Name => children, nested to any depth.
     *
     * @var array<string, array<string, array<string, array<never, never>>>>
     */
    private const CATEGORIES = [
        'Clothing' => ['Men' => [], 'Women' => []],
        'Electronics' => ['Audio' => [], 'Wearables' => []],
        'Home & Living' => [],
        'Accessories' => [],
    ];

    public function run(): void
    {
        $this->settings();
        $this->categories(self::CATEGORIES);
        $this->products();
        $this->menus();
        $this->contactForm();
        $this->storefront();
    }

    private function settings(): void
    {
        $settings = CMS::settings();
        $settings->set('general.app_name', 'Grapes Store');
        $settings->set('commerce.support_email', 'hello@example.com');

        // Everything on, so the demo shows both checkouts; switch them in CMS → Settings → General → Features.
        foreach (['orders_enabled', 'enquiry_enabled', 'pricing_enabled', 'stock_enabled'] as $flag) {
            $settings->set("features.{$flag}", true);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $tree
     */
    private function categories(array $tree, ?int $parentId = null): void
    {
        foreach ($tree as $name => $children) {
            $category = Category::create(['name' => $name, 'parent_id' => $parentId]);
            $this->categories($children, $category->id);
        }
    }

    private function products(): void
    {
        $folder = Media::create(['kind' => 'folder', 'name' => 'Products', 'path' => '']);
        $catalog = app(ProductCatalogService::class);

        $sizes = fn (int $price, ?int $compare) => array_map(fn (string $size, int $index) => [
            'sku' => '{sku}-'.$size, 'option_label' => $size, 'option_values' => ['Size' => $size],
            'price' => $price, 'compare_price' => $compare, 'stock' => [8, 12, 0, 5][$index], 'is_default' => $index === 0,
        ], ['S', 'M', 'L', 'XL'], [0, 1, 2, 3]);

        $products = [
            ['Classic Cotton Tee', 'Basics Co', 'Men', 'TEE-001', 499, 699, 0, true, '👕', 200, 'A soft everyday tee in combed cotton.', $sizes(499, 699)],
            ['Everyday Hoodie', 'Basics Co', 'Women', 'HOOD-001', 1499, null, 0, false, '🧥', 280, 'Warm fleece hoodie with a roomy front pocket.', $sizes(1499, null)],
            ['Linen Summer Shirt', 'Basics Co', 'Men', 'SHIRT-001', 1299, null, 12, false, '👔', 40, 'Breathable linen shirt for warm days.', []],
            ['Wireless Headphones', 'Sonic', 'Audio', 'AUD-001', 2999, 3999, 15, true, '🎧', 255, 'Over-ear Bluetooth headphones with 30 hours of battery.', []],
            ['Bluetooth Speaker', 'Sonic', 'Audio', 'AUD-002', 1999, null, 3, false, '🔊', 330, 'Pocket speaker with deep bass. Only a few left.', []],
            ['Smart Watch', 'Tempo', 'Wearables', 'WEAR-001', 4499, null, 0, false, '⌚', 20, 'Fitness tracking and notifications on your wrist.', []],
            ['Ceramic Coffee Mug', 'Hearth', 'Home & Living', 'HOME-001', 349, null, 40, false, '☕', 25, 'Hand-glazed 350 ml mug, dishwasher safe.', []],
            ['Scented Soy Candle', 'Hearth', 'Home & Living', 'HOME-002', 599, 799, 25, true, '🕯️', 35, 'Slow-burning soy candle with a vanilla and cedar scent.', []],
            ['Bamboo Cutting Board', 'Hearth', 'Home & Living', 'HOME-003', 899, null, 8, false, '🍽️', 100, 'Sturdy bamboo board with a juice groove.', []],
            ['Leather Wallet', 'Tanner', 'Accessories', 'ACC-001', 999, null, 20, true, '👛', 30, 'Slim full-grain leather wallet with six card slots.', []],
            ['Canvas Backpack', 'Tanner', 'Accessories', 'ACC-002', 2199, 2799, 10, true, '🎒', 150, 'Water-resistant canvas backpack with a padded laptop sleeve.', []],
            ['Round Sunglasses', 'Tanner', 'Accessories', 'ACC-003', 1199, null, 18, false, '🕶️', 215, 'UV400 lenses in a lightweight frame.', []],
        ];

        foreach ($products as [$name, $brand, $category, $sku, $price, $compare, $stock, $featured, $emoji, $hue, $summary, $variants]) {
            $variants = array_map(fn (array $variant) => ['sku' => str_replace('{sku}', $sku, $variant['sku'])] + $variant, $variants);

            $catalog->save(new Product, [
                'name' => $name,
                'slug' => '',
                'brand' => $brand,
                'sku' => $sku,
                'short_description' => $summary,
                'description' => $summary."\n\nMade to last, easy to care for and ready to ship from our warehouse.",
                'price' => $price,
                'compare_price' => $compare,
                'stock' => $stock,
                'images' => [$this->picture($folder, Str::slug($name), $name, $emoji, $hue)],
                'is_featured' => $featured,
                'is_active' => true,
                'weight' => 0.5,
                'category_ids' => Category::where('name', $category)->pluck('id')->all(),
            ], $variants === [] ? [] : [['name' => 'Size', 'values' => ['S', 'M', 'L', 'XL']]], $variants);
        }

        // A running offer: shows as a badge and a struck-through price.
        ProductOffer::create([
            'product_id' => Product::where('sku', 'AUD-001')->value('id'),
            'name' => 'Launch sale',
            'discount_type' => 'percentage',
            'value' => 15,
        ]);
    }

    /**
     * Writes an SVG product picture to the media disk, registers it in the media library and returns its path.
     */
    private function picture(Media $folder, string $slug, string $name, string $emoji, int $hue): string
    {
        $path = $folder->name.'/'.$slug.'.svg';
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" width="800" height="800">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="hsl({$hue},70%,88%)"/><stop offset="1" stop-color="hsl({$hue},65%,70%)"/>
  </linearGradient></defs>
  <rect width="800" height="800" fill="url(#g)"/>
  <text x="400" y="440" font-size="300" text-anchor="middle">{$emoji}</text>
  <text x="400" y="690" font-size="44" font-family="sans-serif" font-weight="600" text-anchor="middle" fill="hsl({$hue},60%,22%)">{$name}</text>
</svg>
SVG;

        CMS::mediaDisk()->put($path, $svg);

        Media::create([
            'kind' => 'file',
            'parent_id' => $folder->id,
            'name' => $slug.'.svg',
            'path' => $path,
            'disk' => config('cms.media.disk'),
            'mime' => 'image/svg+xml',
            'size' => strlen($svg),
        ]);

        return $path;
    }

    private function menus(): void
    {
        $primary = Menu::create(['name' => 'Primary']);
        $primary->items()->create(['label' => 'Home', 'url' => '/', 'sort_order' => 0]);

        $shop = $primary->items()->create(['label' => 'Shop', 'url' => '/shop', 'sort_order' => 1]);
        $primary->items()->create(['parent_id' => $shop->id, 'label' => 'All products', 'url' => '/shop', 'sort_order' => 0]);

        // Sub-menus nest to any depth: Shop → Clothing → Men.
        foreach (Category::whereNull('parent_id')->orderBy('id')->get() as $index => $category) {
            $item = $primary->items()->create(['parent_id' => $shop->id, 'label' => $category->name, 'url' => '/shop?category='.$category->slug, 'sort_order' => $index + 1]);

            foreach (Category::where('parent_id', $category->id)->orderBy('id')->get() as $position => $child) {
                $primary->items()->create(['parent_id' => $item->id, 'label' => $child->name, 'url' => '/shop?category='.$child->slug, 'sort_order' => $position]);
            }
        }

        $primary->items()->create(['label' => 'About', 'url' => '/about', 'sort_order' => 2]);
        $primary->items()->create(['label' => 'Contact Us', 'url' => '/contact-us', 'sort_order' => 3]);

        $footer = Menu::create(['name' => 'Footer']);
        foreach ([['Shop', '/shop'], ['About', '/about'], ['Contact Us', '/contact-us']] as $position => [$label, $url]) {
            $footer->items()->create(['label' => $label, 'url' => $url, 'sort_order' => $position]);
        }
    }

    /**
     * The form the contact page posts to (CMS → Forms), with a rule per field.
     */
    private function contactForm(): void
    {
        $form = Form::create(['name' => 'contact']);

        foreach (['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:30'], 'message' => ['required', 'string', 'max:2000']] as $field => $rules) {
            FormFieldRule::create(['form_id' => $form->id, 'field' => $field, 'rules' => $rules]);
        }
    }

    /**
     * The layout, components and pages in database/seeders/storefront, loaded the way CMS → Import Site loads a zip, without the backup (the database is new).
     */
    private function storefront(): void
    {
        CMS::importSite(__DIR__.'/storefront', backup: false);
    }
}
