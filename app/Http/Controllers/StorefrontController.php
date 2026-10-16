<?php

namespace App\Http\Controllers;

use App\View\Components\ProductCard;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Listing pages render every matching product; public/assets/js/main.js then
 * filters, sorts and paginates them in the browser.
 */
class StorefrontController extends Controller
{
    /** Products added within this many days count as new arrivals. */
    public const NEW_ARRIVAL_DAYS = 30;

    private const HOME_SECTION_SIZE = 4;

    /** Top-level categories with their own listing page, each routed as "shop.{slug}". */
    private const CATEGORY_PAGES = ['girls', 'boys', 'men', 'women'];

    /** How many products from the same subcategory a product page suggests. */
    private const RELATED_PRODUCTS = 4;

    /** The home page's "Shop for …" rows, in display order, keyed by top-level category slug. */
    private const HOME_SHOP_SECTIONS = [
        'girls' => 'Shop for Girls',
        'boys' => 'Shop for Boys',
        'men' => 'Shop for Men',
        'women' => 'Shop for Women',
    ];

    /** Kids' rows are a carousel of category cards; the rest keep the intro panel and tile grid. */
    private const HOME_CAROUSEL_SECTIONS = ['girls', 'boys'];

    public function home(): View
    {
        return view('shop.home', [
            'shopSections' => $this->shopSections(),
            'newArrivals' => $this->newArrivalsQuery()->take(self::HOME_SECTION_SIZE)->get(),
            'bestsellers' => $this->productQuery()->featured()->take(self::HOME_SECTION_SIZE)->get(),
        ]);
    }

    public function men(): View
    {
        return $this->category('men');
    }

    public function women(): View
    {
        return $this->category('women');
    }

    public function boys(): View
    {
        return $this->category('boys');
    }

    public function girls(): View
    {
        return $this->category('girls');
    }

    public function newArrivals(): View
    {
        return view('shop.new-arrivals', ['products' => $this->newArrivalsQuery()->get()]);
    }

    public function search(): View
    {
        return view('shop.search', ['products' => $this->productQuery()->get()]);
    }

    /** The wishlist lives in the browser, so the page renders every product and the script shows the saved ones. */
    public function wishlist(): View
    {
        return view('shop.wishlist', ['products' => $this->productQuery()->get()]);
    }

    /** A product's own page; inactive products, or ones in an inactive category, are not found. */
    public function product(string $slug): View
    {
        $product = $this->productQuery()->where('slug', $slug)->firstOrFail();

        $card = new ProductCard($product);

        return view('shop.product', [
            'product' => $product,
            'card' => $card,
            'categoryUrl' => in_array($card->categorySlug, self::CATEGORY_PAGES, true) ? route('shop.'.$card->categorySlug) : null,
            'gallery' => $product->galleryFor(),
            'related' => $this->productQuery()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->id)
                ->take(self::RELATED_PRODUCTS)
                ->get(),
        ]);
    }

    private function category(string $slug): View
    {
        $category = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->where('slug', $slug)
            ->with(['children' => fn ($children) => $children->active()])
            ->firstOrFail();

        return view('shop.category', [
            'category' => $category,
            'products' => $this->productQuery()
                ->whereIn('category_id', $category->children->pluck('id')->push($category->id))
                ->get(),
        ]);
    }

    /**
     * Each active top-level category, introduced by its name and description, with
     * its active subcategories as tiles. A tile links to its parent's listing with
     * the subcategory filter preselected.
     *
     * @return list<array{title: string, name: string, description: ?string, url: string, layout: 'carousel'|'band', tiles: list<array{name: string, image: ?string, url: string}>}>
     */
    private function shopSections(): array
    {
        $parents = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->whereIn('slug', array_keys(self::HOME_SHOP_SECTIONS))
            ->with(['children' => fn ($children) => $children->active()->with('images')])
            ->get()
            ->keyBy('slug');

        $sections = [];

        foreach (self::HOME_SHOP_SECTIONS as $slug => $title) {
            $parent = $parents->get($slug);

            if ($parent === null || $parent->children->isEmpty()) {
                continue;
            }

            $parentUrl = route('shop.'.$slug);

            $sections[] = [
                'title' => $title,
                'name' => $parent->name,
                'description' => $parent->description,
                'url' => $parentUrl,
                'layout' => in_array($slug, self::HOME_CAROUSEL_SECTIONS, true) ? 'carousel' : 'band',
                'tiles' => $parent->children->map(fn (Category $subcategory): array => [
                    'name' => $subcategory->name,
                    'image' => $subcategory->imageUrl(),
                    'url' => $parentUrl.'#'.Str::after($subcategory->slug, $slug.'-'),
                ])->all(),
            ];
        }

        return $sections;
    }

    /**
     * @return Builder<Product>
     */
    private function newArrivalsQuery(): Builder
    {
        return $this->productQuery()
            ->where('created_at', '>=', now()->subDays(self::NEW_ARRIVAL_DAYS))
            ->reorder()
            ->latest()
            ->orderBy('id');
    }

    /**
     * Active products in active categories, with what a product card needs, in catalogue order.
     *
     * @return Builder<Product>
     */
    private function productQuery(): Builder
    {
        return Product::query()
            ->active()
            ->whereHas('category', fn (Builder $category) => $category->active())
            ->with([
                'category.parent',
                'images',
                'variants' => fn ($variants) => $variants->orderBy('id'),
            ])
            ->orderBy('id');
    }
}
