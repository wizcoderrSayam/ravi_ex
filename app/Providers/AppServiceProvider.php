<?php

namespace App\Providers;

use Carepeerinfotech\Ecommerce\Models\Image;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
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
        Product::saved(fn () => $this->deleteImagesOfDeletedVariants());
    }

    /**
     * Saving a product deletes the variants removed from its form in one query, which skips
     * the variant's own image clean-up. The catalogue updates the product again after syncing
     * its variants, so this runs then and deletes those images one by one, files included.
     */
    private function deleteImagesOfDeletedVariants(): void
    {
        Image::query()
            ->where('imageable_type', (new ProductVariant)->getMorphClass())
            ->whereNotIn('imageable_id', ProductVariant::query()->select('id'))
            ->get()
            ->each->delete();
    }
}
