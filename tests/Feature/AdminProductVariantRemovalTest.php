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
use Illuminate\Support\Facades\Storage;
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

    public function test_the_edit_form_has_a_remove_button_and_an_unticked_value_for_each_variant(): void
    {
        $variantCount = $this->product->variants()->count();

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $this->product));
        $html = $response->getContent();

        $response->assertOk();
        $this->assertSame($variantCount, substr_count($html, '<button type="button" data-remove-variant aria-label="Remove variant '));
        foreach ($this->product->variants as $variant) {
            $response->assertSee('<tr data-variant-id="'.$variant->id.'">', false);
        }
        // the hidden 0 comes first, so an unticked box still sends a value and a ticked one overrides it
        $this->assertSame($variantCount, preg_match_all('/<input type="hidden" name="(variants\[\d+\]\[is_active\])" value="0"><input type="checkbox" name="\1" value="1" data-bool/', $html));
        $response->assertSee('<input type="hidden" name="is_active" value="0">', false);
        $response->assertSee('<input type="hidden" name="is_featured" value="0">', false);
    }

    public function test_a_zero_for_a_variants_active_field_saves_it_as_inactive(): void
    {
        $variants = $this->product->variants()->orderBy('id')->get();
        $unticked = $variants->firstWhere('option_label', 'M');

        $payload = $this->formPayload($variants);
        $index = $variants->search(fn (ProductVariant $variant): bool => $variant->is($unticked));
        $payload['variants'][$index]['is_active'] = '0';

        $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->product), $payload)->assertOk();

        $this->assertFalse($unticked->fresh()->is_active);
        $this->assertSame($variants->count() - 1, $this->product->variants()->where('is_active', true)->count());
    }

    public function test_removing_a_variant_deletes_its_images_and_their_files(): void
    {
        Storage::fake('public');
        $variants = $this->product->variants()->orderBy('id')->get();
        $removed = $variants->firstWhere('option_label', 'M');
        $kept = $variants->firstWhere('option_label', 'L');
        Storage::disk('public')->put('variants/m.jpg', 'm');
        Storage::disk('public')->put('variants/l.jpg', 'l');
        $removedImage = $removed->images()->create(['collection' => 'image', 'disk' => 'public', 'path' => 'variants/m.jpg']);
        $keptImage = $kept->images()->create(['collection' => 'image', 'disk' => 'public', 'path' => 'variants/l.jpg']);

        $this->updateWith($variants->reject(fn (ProductVariant $variant): bool => $variant->is($removed)))
            ->assertOk();

        $this->assertModelMissing($removedImage);
        Storage::disk('public')->assertMissing('variants/m.jpg');
        $this->assertModelExists($keptImage);
        Storage::disk('public')->assertExists('variants/l.jpg');
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
        return $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->product), $this->formPayload($variants));
    }

    /**
     * The product form's fields, with one row per given variant.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<string, mixed>
     */
    private function formPayload(Collection $variants): array
    {
        return [
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
        ];
    }
}
