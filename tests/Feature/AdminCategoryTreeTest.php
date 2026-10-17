<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\Role;
use Carepeerinfotech\Ecommerce\Services\Catalog\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EcommerceSeeder::class);
        Category::query()->delete();

        $this->admin = User::factory()->create()->fresh();
        $this->admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));
    }

    public function test_subcategories_are_listed_beneath_their_parent_category(): void
    {
        $boys = Category::create(['name' => 'Boys', 'sort_order' => 0]);
        $men = Category::create(['name' => 'Men', 'sort_order' => 1]);
        $this->createSubcategory($men, 'Waistcoat Sets', 'men-waistcoat-sets', 0);
        $this->createSubcategory($boys, 'Kurta Pajama', 'boys-kurta-pajama', 1);
        $this->createSubcategory($boys, 'Sherwani', 'boys-sherwani', 0);

        $response = $this->actingAs($this->admin)->get(route('admin.categories.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['2 categories', '3 subcategories', 'Boys', '2 subcategories']);
        $response->assertSeeInOrder(['Boys', 'Sherwani', 'boys-sherwani', 'Kurta Pajama', 'Men', 'Waistcoat Sets', 'men-waistcoat-sets']);
        $response->assertSee(route('admin.categories.create', ['parent' => $boys->id]), false);
    }

    public function test_add_subcategory_link_preselects_the_parent(): void
    {
        $men = Category::create(['name' => 'Men']);

        $this->actingAs($this->admin)
            ->get(route('admin.categories.create', ['parent' => $men->id]))
            ->assertOk()
            ->assertSee('<option value="'.$men->id.'" selected>Men</option>', false);
    }

    public function test_a_category_can_be_created_as_a_subcategory(): void
    {
        $men = Category::create(['name' => 'Men']);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Sherwani', 'slug' => 'men-sherwani', 'parent_id' => $men->id])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSame($men->id, Category::firstWhere('slug', 'men-sherwani')->parent_id);
    }

    public function test_a_subcategory_cannot_be_used_as_a_parent(): void
    {
        $men = Category::create(['name' => 'Men']);
        $sherwani = $this->createSubcategory($men, 'Sherwani', 'men-sherwani', 0);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.store'), ['name' => 'Wedding Sherwani', 'parent_id' => $sherwani->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Wedding Sherwani']);
    }

    public function test_a_category_with_subcategories_cannot_become_a_subcategory(): void
    {
        $men = Category::create(['name' => 'Men']);
        $boys = Category::create(['name' => 'Boys']);
        $this->createSubcategory($boys, 'Sherwani', 'boys-sherwani', 0);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $boys), ['name' => 'Boys', 'slug' => 'boys', 'parent_id' => $men->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($boys->fresh()->parent_id);
    }

    public function test_a_category_with_subcategories_keeps_top_level_when_saved_from_its_locked_form(): void
    {
        $boys = Category::create(['name' => 'Boys']);
        $this->createSubcategory($boys, 'Sherwani', 'boys-sherwani', 0);

        $this->actingAs($this->admin)
            ->get(route('admin.categories.edit', $boys))
            ->assertOk()
            ->assertSee('so it stays top level')
            ->assertDontSee('name="parent_id"', false);

        // The disabled select is not submitted, so parent_id is absent from the request.
        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $boys), ['name' => 'Boys Wear', 'slug' => 'boys'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSame('Boys Wear', $boys->fresh()->name);
        $this->assertNull($boys->fresh()->parent_id);
    }

    public function test_product_form_keeps_an_inactive_category_selected(): void
    {
        $men = Category::create(['name' => 'Men']);
        $retired = Category::create(['name' => 'Retired Line', 'slug' => 'men-retired-line', 'parent_id' => $men->id, 'is_active' => false]);
        $product = app(ProductCatalogService::class)->save(new Product, [
            'category_id' => $retired->id, 'name' => 'Old Sherwani', 'slug' => 'old-sherwani', 'sku' => 'RE-9001', 'price' => 100, 'stock' => 1, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('<option value="'.$retired->id.'" selected>Men › Retired Line (inactive)</option>', false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertDontSee('Retired Line');
    }

    public function test_editing_a_subcategory_can_move_it_to_top_level(): void
    {
        $men = Category::create(['name' => 'Men']);
        $sherwani = $this->createSubcategory($men, 'Sherwani', 'men-sherwani', 0);

        $this->actingAs($this->admin)
            ->put(route('admin.categories.update', $sherwani), ['name' => 'Sherwani', 'slug' => 'men-sherwani', 'parent_id' => ''])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertNull($sherwani->fresh()->parent_id);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $men = Category::create(['name' => 'Men']);
        $sherwani = $this->createSubcategory($men, 'Sherwani', 'men-sherwani', 0);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.categories.destroy', $men))
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        $this->assertModelExists($men);
        $this->assertSame($men->id, $sherwani->fresh()->parent_id);
    }

    public function test_product_form_names_subcategories_with_their_parent(): void
    {
        $men = Category::create(['name' => 'Men', 'sort_order' => 0]);
        $boys = Category::create(['name' => 'Boys', 'sort_order' => 1]);
        $this->createSubcategory($men, 'Indo-Westerns', 'men-indo-westerns', 0);
        $this->createSubcategory($boys, 'Indo-Westerns', 'boys-indo-westerns', 0);

        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSeeInOrder(['>Men<', '>Men › Indo-Westerns<', '>Boys<', '>Boys › Indo-Westerns<'], false);
    }

    private function createSubcategory(Category $parent, string $name, string $slug, int $sortOrder): Category
    {
        return Category::create(['name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder, 'parent_id' => $parent->id]);
    }
}
