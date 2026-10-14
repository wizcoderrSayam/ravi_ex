<?php

namespace App\Http\Controllers;

use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Eloquent\Builder;
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

    public function home(): View
    {
        return view('shop.home', [
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

    public function accessories(): View
    {
        return $this->category('accessories');
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
