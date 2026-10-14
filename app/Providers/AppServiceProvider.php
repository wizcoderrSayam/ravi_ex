<?php

namespace App\Providers;

use Carepeerinfotech\Ecommerce\Models\Category;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $this->registerCategoryHierarchy();
    }

    /**
     * The package's Category model is flat; this app adds a parent_id column,
     * so the parent/children relations are attached here instead of in the package.
     */
    private function registerCategoryHierarchy(): void
    {
        Category::resolveRelationUsing('parent', fn (Category $category): BelongsTo => $category->belongsTo(Category::class, 'parent_id'));

        Category::resolveRelationUsing('children', fn (Category $category): HasMany => $category->hasMany(Category::class, 'parent_id')->orderBy('sort_order'));
    }
}
