<?php

namespace App\Providers;

use Carepeerinfotech\Ecommerce\Models\Category;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $this->registerCategoryHierarchy();
        $this->shareAdminCategoryTree();
    }

    /**
     * The package's admin category list is flat and paginated. The app's override
     * of that view (resources/views/vendor/ecommerce) renders each top-level
     * category with its subcategories nested beneath it instead.
     */
    private function shareAdminCategoryTree(): void
    {
        View::composer('ecommerce::admin.categories.index', function (ViewContract $view): void {
            $view->with('categoryTree', Category::query()
                ->whereNull('parent_id')
                ->withCount('products')
                ->with(['children' => fn (HasMany $children) => $children->withCount('products')->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get());
        });
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
