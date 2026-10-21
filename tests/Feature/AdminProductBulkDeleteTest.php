<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\Role;
use Database\Seeders\StorefrontCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EcommerceSeeder::class);
        $this->seed(StorefrontCatalogSeeder::class);

        $this->admin = User::factory()->create()->fresh();
        $this->admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));
    }

    public function test_the_product_list_has_a_delete_selected_button(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('id="bulk-delete" disabled data-url="'.route('admin.products.bulk-destroy').'"', false);
    }

    public function test_the_selected_products_and_their_variants_are_deleted(): void
    {
        $deleted = Product::query()->orderBy('id')->take(3)->get();
        $kept = Product::query()->orderBy('id')->skip(3)->first();

        $this->actingAs($this->admin)
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => $deleted->modelKeys()])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => '3 products deleted.']);

        foreach ($deleted as $product) {
            $this->assertModelMissing($product);
            $this->assertDatabaseMissing('product_variants', ['product_id' => $product->id]);
        }
        $this->assertModelExists($kept);
    }

    public function test_nothing_is_deleted_without_a_selection(): void
    {
        $count = Product::count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->assertSame($count, Product::count());
    }

    public function test_a_shopper_cannot_bulk_delete(): void
    {
        $product = Product::query()->first();

        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.products.bulk-destroy'), ['ids' => [$product->id]])
            ->assertForbidden();

        $this->assertModelExists($product);
    }
}
