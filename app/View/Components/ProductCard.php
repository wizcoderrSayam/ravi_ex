<?php

namespace App\View\Components;

use App\Http\Controllers\StorefrontController;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Carepeerinfotech\Ecommerce\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * A storefront product card. The data-* attributes it renders are what
 * public/assets/js/main.js reads for filtering, quick view, cart and wishlist.
 *
 * Expects the product's category.parent, images and variants to be eager loaded.
 */
class ProductCard extends Component
{
    /** Background tints shown behind a card while its photo loads. */
    private const TINTS = ['#8B6BA8', '#1E2F5C', '#1F5C55', '#24483A', '#A91E23', '#BFAE8E', '#C77D3A', '#5C2A4A'];

    public string $code;

    public string $categorySlug;

    public string $categoryName;

    public string $subcategorySlug;

    public string $subcategoryName;

    public float $price;

    public ?float $comparePrice;

    public int $discountPercent;

    public bool $isNew;

    public bool $inStock;

    public string $fabric;

    public string $sizes;

    public string $searchText;

    public string $tint;

    public ?string $imageUrl;

    public function __construct(public Product $product)
    {
        $category = $product->category;
        $parent = $category?->parent;

        // Products normally sit in a subcategory; one filed directly under a top-level category has no subcategory.
        $top = $parent ?? $category;
        $this->categorySlug = $top?->slug ?? '';
        $this->categoryName = $top?->name ?? '';
        $this->subcategorySlug = $parent ? Str::after($category->slug, $parent->slug.'-') : '';
        $this->subcategoryName = $parent ? $category->name : '';

        // The product SKU is its default variant's, e.g. "RE-0035-S"; the product code drops the size.
        $defaultLabel = $product->defaultVariant()?->option_label;
        $this->code = $defaultLabel ? Str::chopEnd((string) $product->sku, '-'.$defaultLabel) : (string) $product->sku;

        $this->price = $product->minPrice();
        $this->comparePrice = (float) $product->compare_price > $this->price ? (float) $product->compare_price : null;
        $this->discountPercent = $this->comparePrice ? (int) round((1 - $this->price / $this->comparePrice) * 100) : 0;
        $this->isNew = $product->created_at?->gte(now()->subDays(StorefrontController::NEW_ARRIVAL_DAYS)) ?? false;
        $this->inStock = $product->inStock();

        // The short description reads "Fabric: Silk" (or "Material: …" for accessories).
        $this->fabric = Str::contains((string) $product->short_description, ': ') ? Str::after($product->short_description, ': ') : '';
        $this->sizes = $product->variants
            ->where('is_active', true)
            ->map(fn (ProductVariant $variant): string => $variant->option_label ?: 'Free Size')
            ->implode('|');
        $this->searchText = Str::lower(implode(' ', array_filter([$product->name, $this->subcategoryName, $this->categoryName, $this->fabric])));
        $this->tint = self::TINTS[$product->id % count(self::TINTS)];
        $this->imageUrl = $product->primaryImageUrl();
    }

    public function money(float $amount): string
    {
        return Money::store($amount, 0);
    }

    public function render(): View
    {
        return view('components.product-card');
    }
}
