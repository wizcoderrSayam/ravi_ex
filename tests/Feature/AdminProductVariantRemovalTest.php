<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Carepeerinfotech\Ecommerce\Models\Role;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminProductVariantRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EcommerceSeeder::class);
        $this->seed(StorefrontCatalogSeeder::class);

        $this->admin = User::factory()->create()->fresh();
        $this->admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        $this->product = Product::query()->where('name', 'Festive Co-ord Set')->firstOrFail();
    }

    public function test_a_variant_left_out_of_the_saved_form_is_deleted(): void
    {
        $variants = $this->product->variants()->orderBy('id')->get();
        $default = $variants->firstWhere('is_default', true);
        $removed = $variants->firstWhere('option_label', 'M');

        $this->updateWith($variants->reject(fn (ProductVariant $variant): bool => $variant->is($removed)))
            ->assertOk();

        $this->assertModelMissing($removed);
        $this->assertSame($variants->count() - 1, $this->product->variants()->count());
        $this->assertTrue($default->fresh()->is_default);
    }

    public function test_removing_the_default_variant_makes_another_one_the_default(): void
    {
        $variants = $this->product->variants()->orderBy('id')->get();
        $default = $variants->firstWhere('is_default', true);

        $this->updateWith($variants->reject(fn (ProductVariant $variant): bool => $variant->is($default)))
            ->assertOk();

        $this->assertModelMissing($default);
        $this->assertSame(1, $this->product->variants()->where('is_default', true)->count());
    }

    public function test_a_saved_product_cannot_lose_its_last_variant(): void
    {
        $this->updateWith(collect())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants');

        $this->assertGreaterThan(0, $this->product->variants()->count());
    }

    /**
     * Saves the product form with only the given variant rows.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function updateWith(Collection $variants): TestResponse
    {
        return $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->product), [
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'category_id' => $this->product->category_id,
            'is_active' => 1,
            'variants' => $variants->values()->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'option_label' => $variant->option_label,
                'price' => $variant->price,
                'stock' => $variant->stock,
                'is_default' => $variant->is_default ? 1 : 0,
                'is_active' => 1,
            ])->all(),
        ]);
    }
}
