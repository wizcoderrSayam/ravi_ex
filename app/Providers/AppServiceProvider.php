<?php

namespace App\Providers;

use App\View\Components\ProductCard;
use Carepeerinfotech\Ecommerce\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The header's search box lives on every page, so it gets the catalogue index via a composer rather than each controller.
        View::composer('partials.header', fn ($view) => $view->with('searchIndex', $this->searchIndex()));
    }

    /**
     * What the header's instant-search box filters client-side: just enough about
     * each active product (in an active category) to list and link to it.
     *
     * @return list<array{name: string, url: string, image: ?string, price: string, search: string}>
     */
    private function searchIndex(): array
    {
        return Product::query()
            ->active()
            ->whereHas('category', fn (Builder $category) => $category->active())
            ->with(['category.parent', 'images', 'variants' => fn ($variants) => $variants->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->map(function (Product $product): array {
                $card = new ProductCard($product);

                return [
                    'name' => $product->name,
                    'url' => $card->url,
                    'image' => $card->imageUrl,
                    'price' => $card->money($card->price),
                    'search' => $card->searchText,
                ];
            })
            ->all();
    }
}
