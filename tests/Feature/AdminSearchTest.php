<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Models\Order;
use Peer\Ecommerce\Models\Permission;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;
use Tests\TestCase;

/**
 * The store's records in the panel's search (the header's search box, ⌘K),
 * registered through the ecommerce package's AdminPanel contract.
 */
class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));
    }

    public function test_a_product_is_found_by_its_sku_with_its_picture_and_opens_its_edit_screen(): void
    {
        $product = $this->product('Zebracorn Tee', 'ZBR-7781', ['products/tee.png']);

        $result = $this->search($this->superAdmin(), 'zbr-7781', 'products')[0];

        $this->assertSame('Zebracorn Tee', $result['title']);
        $this->assertSame(route('admin.products.edit', $product), $result['url']);
        $this->assertStringEndsWith('/products/tee.png', $result['image']);
        $this->assertStringStartsWith('ZBR-7781 · ', $result['subtitle']);
    }

    public function test_an_order_is_found_by_its_customers_email_and_opens_the_order(): void
    {
        $order = $this->order('asha.zebracorn@example.com');

        $result = $this->search($this->superAdmin(), 'asha.zebracorn', 'orders')[0];

        $this->assertSame($order->order_number, $result['title']);
        $this->assertSame(route('admin.orders.show', $order), $result['url']);
        $this->assertSame('Pending', $result['badge']);
        $this->assertSame(['field' => 'Email', 'text' => 'asha.zebracorn@example.com', 'line' => null, 'count' => 1, 'code' => false], $result['match']);
    }

    public function test_a_user_is_found_by_phone_but_never_by_their_password_hash(): void
    {
        $user = User::factory()->create(['name' => 'Ravi Kumar', 'phone' => '+91 90000 12345']);

        $results = $this->search($this->superAdmin(), '90000 12345', 'users');

        $this->assertSame(['Ravi Kumar'], array_column($results, 'title'));
        $this->assertSame(route('admin.users.edit', $user), $results[0]['url']);
        $this->assertSame([], $this->search($this->superAdmin(), substr($user->password, 10, 20), 'users'));
    }

    public function test_a_category_opens_its_store_categories_screen(): void
    {
        $category = Category::create(['name' => 'Zebracorn Feed']);

        $result = $this->search($this->superAdmin(), 'zebracorn', 'categories')[0];

        $this->assertSame(route('admin.categories.edit', $category->id), $result['url']);
    }

    public function test_a_role_only_searches_the_records_its_permissions_open(): void
    {
        $this->product('Zebracorn Mug', 'MUG-1');
        $this->order('zebracorn@example.com');
        User::factory()->create(['email' => 'zebracorn.customer@example.com']);
        $editor = $this->adminWith('products.manage');

        $groups = $this->getJsonAs($editor, 'zebracorn')->json('groups');

        $this->assertSame(['products'], array_values(array_intersect(array_column($groups, 'key'), ['products', 'orders', 'users'])));
    }

    public function test_orders_are_not_searched_while_the_orders_feature_is_off(): void
    {
        $this->order('zebracorn@example.com');
        CMS::settings()->set('features.orders_enabled', false);

        $this->assertSame([], $this->search($this->superAdmin(), 'zebracorn', 'orders'));
    }

    /**
     * One source's results for $query.
     *
     * @return list<array<string, mixed>>
     */
    private function search(User $user, string $query, string $source): array
    {
        foreach ($this->getJsonAs($user, $query, $source)->json('groups') as $group) {
            if ($group['key'] === $source) {
                return $group['results'];
            }
        }

        return [];
    }

    private function getJsonAs(User $user, string $query, ?string $source = null): TestResponse
    {
        return $this->actingAs($user)
            ->getJson(route('cms.panel.search.results', array_filter(['q' => $query, 'in' => $source])))
            ->assertOk();
    }

    /**
     * @param  list<string>  $images
     */
    private function product(string $name, string $sku, array $images = []): Product
    {
        foreach ($images as $path) {
            Storage::disk(config('cms.media.disk'))->put($path, 'image');
        }

        return app(ProductCatalogService::class)->save(new Product, [
            'name' => $name, 'slug' => '', 'sku' => $sku, 'price' => 499, 'stock' => 5, 'is_active' => true, 'images' => $images,
        ]);
    }

    private function order(string $email): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.strtoupper(fake()->unique()->bothify('????####')),
            'customer_name' => 'Asha', 'customer_email' => $email, 'customer_phone' => '9876543210',
            'shipping_address' => '1 Main St', 'shipping_city' => 'Delhi', 'shipping_state' => 'Delhi', 'shipping_postal_code' => '110001', 'shipping_country' => 'IN',
            'subtotal' => 499, 'total' => 499, 'status' => 'pending',
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_active' => true]));

        return $admin;
    }

    private function adminWith(string $permission): User
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'is_active' => true]);
        $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission, 'group' => 'Catalog']));
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach($role);

        return $admin;
    }
}
