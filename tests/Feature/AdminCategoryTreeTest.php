<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Category;
use Carepeerinfotech\Ecommerce\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_subcategories_are_listed_beneath_their_parent_category(): void
    {
        $this->seed(EcommerceSeeder::class);
        Category::query()->delete();

        $boys = Category::create(['name' => 'Boys', 'sort_order' => 0]);
        $men = Category::create(['name' => 'Men', 'sort_order' => 1]);
        $this->createSubcategory($men, 'Waistcoat Sets', 'men-waistcoat-sets', 0);
        $this->createSubcategory($boys, 'Kurta Pajama', 'boys-kurta-pajama', 1);
        $this->createSubcategory($boys, 'Sherwani', 'boys-sherwani', 0);

        $admin = User::factory()->create()->fresh();
        $admin->roles()->attach(Role::firstWhere('slug', 'super-admin'));

        $response = $this->actingAs($admin)->get(route('admin.categories.index'));

        $response->assertOk();
        $response->assertSee('2 subcategories');
        $response->assertSeeInOrder(['Boys', 'Sherwani', 'boys-sherwani', 'Kurta Pajama', 'Men', 'Waistcoat Sets', 'men-waistcoat-sets']);
    }

    private function createSubcategory(Category $parent, string $name, string $slug, int $sortOrder): void
    {
        $subcategory = Category::create(['name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder]);
        $subcategory->forceFill(['parent_id' => $parent->id])->save();
    }
}
