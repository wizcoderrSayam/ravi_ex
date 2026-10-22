<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Category;
use Peer\Ecommerce\Http\Controllers\Api\StoreController;
use Peer\Ecommerce\Models\Enquiry;
use Peer\Ecommerce\Models\Order;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\ProductOffer;
use Peer\Ecommerce\Services\Catalog\ProductCatalogService;
use Tests\TestCase;

class StorefrontApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-cart-token-0001';

    private function product(string $name, float $price = 100, int $stock = 5, array $categoryIds = []): Product
    {
        return app(ProductCatalogService::class)->save(new Product, [
            'name' => $name, 'slug' => '', 'sku' => strtoupper(substr($name, 0, 3)).'-'.random_int(100, 999),
            'price' => $price, 'stock' => $stock, 'is_active' => true, 'category_ids' => $categoryIds,
        ]);
    }

    private function cartHeaders(): array
    {
        return ['X-Cart-Token' => self::TOKEN];
    }

    /**
     * @return array<string, string>
     */
    private function customer(): array
    {
        return ['name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '9999999999', 'address' => '1 Main St', 'city' => 'Delhi', 'state' => 'DL', 'postal_code' => '110001'];
    }

    public function test_products_filter_by_ids_search_and_category_tree(): void
    {
        $shirts = Category::create(['name' => 'Shirts']);
        $men = Category::create(['name' => 'Men', 'parent_id' => $shirts->id]);
        $tee = $this->product('Blue Tee', categoryIds: [$men->id]);
        $this->product('Red Mug');

        $this->getJson('/api/products?ids='.$tee->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Blue Tee');
        $this->getJson('/api/products?search=mug')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Red Mug');
        $this->getJson('/api/products?category=shirts')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Blue Tee');
        $this->getJson('/api/products?category=nope')->assertJsonCount(0, 'data');
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Red Mug');
        $this->getJson('/api/products?sort=oldest')->assertJsonPath('data.0.name', 'Blue Tee');
        $this->getJson('/api/categories')->assertJsonPath('data.0.children.0.slug', 'men');
    }

    public function test_a_product_is_found_by_its_id_or_its_slug(): void
    {
        $tee = $this->product('Blue Tee');
        $hidden = $this->product('Old Mug');
        $hidden->update(['is_active' => false]);

        $this->getJson('/api/products/'.$tee->id)->assertOk()->assertJsonPath('data.name', 'Blue Tee')->assertJsonPath('data.slug', 'blue-tee');
        $this->getJson('/api/products/blue-tee')->assertOk()->assertJsonPath('data.id', $tee->id);
        $this->getJson('/api/products/old-mug')->assertNotFound();
        $this->getJson('/api/products/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/products/no-such-product')->assertNotFound();
    }

    public function test_prices_and_stock_follow_their_feature_flags(): void
    {
        $this->product('Blue Tee', 250, 7);

        $this->getJson('/api/products')->assertJsonPath('data.0.price_label', '₹250.00')->assertJsonPath('data.0.stock', 7);

        CMS::settings()->set('features.pricing_enabled', false);
        CMS::settings()->set('features.stock_enabled', false);

        $this->getJson('/api/products')->assertJsonMissingPath('data.0.price_label')->assertJsonMissingPath('data.0.stock')->assertJsonMissingPath('data.0.in_stock');
        $this->getJson('/api/store')->assertJsonPath('data.pricing', false)->assertJsonPath('data.stock', false);
    }

    public function test_the_cart_adds_updates_and_removes_lines_and_respects_stock(): void
    {
        $product = $this->product('Blue Tee', 100, 3);

        $this->getJson('/api/cart')->assertStatus(422);

        $item = $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2], $this->cartHeaders())
            ->assertCreated()->assertJsonPath('data.count', 2)->assertJsonPath('data.totals.subtotal_label', '₹200.00')
            ->json('data.items.0.id');

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2], $this->cartHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->patchJson("/api/cart/items/{$item}", ['quantity' => 3], $this->cartHeaders())->assertOk()->assertJsonPath('data.count', 3);
        $this->deleteJson("/api/cart/items/{$item}", [], $this->cartHeaders())->assertOk()->assertJsonPath('data.count', 0);
    }

    public function test_the_cart_is_gone_while_both_checkouts_are_off(): void
    {
        CMS::settings()->set('features.orders_enabled', false);

        $this->getJson('/api/cart', $this->cartHeaders())->assertNotFound();
    }

    public function test_an_order_is_placed_from_the_cart_and_takes_the_stock(): void
    {
        $product = $this->product('Blue Tee', 100, 5);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2], $this->cartHeaders());

        $this->postJson('/api/orders', ['name' => ''] + $this->customer(), $this->cartHeaders())->assertUnprocessable();

        $number = $this->postJson('/api/orders', $this->customer(), $this->cartHeaders())->assertCreated()->json('data.number');

        $order = Order::where('order_number', $number)->with('items')->firstOrFail();
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('49.00', $order->shipping_amount);
        $this->assertSame('36.00', $order->tax_amount);
        $this->assertSame('285.00', $order->total);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame(3, $product->variants()->first()->stock);
        $this->assertSame(3, $product->fresh()->stock);
        $this->getJson('/api/cart', $this->cartHeaders())->assertJsonPath('data.count', 0);
        $this->postJson('/api/orders', $this->customer(), $this->cartHeaders())->assertUnprocessable()->assertJsonValidationErrors('cart');
    }

    public function test_an_order_can_ship_to_a_different_address_than_its_billing_address(): void
    {
        $product = $this->product('Blue Tee', 100, 5);
        $this->postJson('/api/cart/items', ['product_id' => $product->id], $this->cartHeaders());

        $this->postJson('/api/orders', $this->customer() + ['ship_to_different_address' => true], $this->cartHeaders())
            ->assertUnprocessable()->assertJsonValidationErrors(['shipping_name', 'shipping_address', 'shipping_city', 'shipping_state', 'shipping_postal_code']);

        $number = $this->postJson('/api/orders', $this->customer() + [
            'ship_to_different_address' => true, 'shipping_name' => 'Ravi', 'shipping_address' => '9 Mall Road',
            'shipping_city' => 'Amritsar', 'shipping_state' => 'PB', 'shipping_postal_code' => '143001',
        ], $this->cartHeaders())->assertCreated()->json('data.number');

        $order = Order::where('order_number', $number)->firstOrFail();
        $this->assertFalse($order->billing_same_as_shipping);
        $this->assertSame(['Asha', '1 Main St', 'Delhi', 'IN'], [$order->billing_name, $order->billing_address, $order->billing_city, $order->billing_country]);
        $this->assertSame(['Ravi', '9 Mall Road', 'Amritsar', 'IN'], [$order->shipping_name, $order->shipping_address, $order->shipping_city, $order->shipping_country]);
    }

    public function test_categories_list_in_creation_order_with_their_pictures(): void
    {
        Storage::fake(config('cms.media.disk'), ['url' => 'http://localhost/media']);
        $bolus = Category::create(['name' => 'Bolus']);
        Category::create(['name' => 'Antibiotics']);
        Storage::disk(config('cms.media.disk'))->put('Categories/'.$bolus->slug.'.jpg', 'jpeg');

        $this->getJson('/api/categories')
            ->assertJsonPath('data.0.name', 'Bolus')
            ->assertJsonPath('data.0.image', '/media/Categories/bolus.jpg')
            ->assertJsonPath('data.1.image', null);
    }

    public function test_a_customer_tracks_an_order_by_its_number_and_email(): void
    {
        $product = $this->product('Blue Tee', 100, 5);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2], $this->cartHeaders());
        $number = $this->postJson('/api/orders', $this->customer(), $this->cartHeaders())->json('data.number');

        $this->postJson('/api/orders/track', ['number' => strtolower($number), 'email' => 'ASHA@example.com'])
            ->assertOk()
            ->assertJsonPath('data.number', $number)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_method_label', 'Cash on delivery')
            ->assertJsonPath('data.items.0.name', 'Blue Tee')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.url', '/product/'.$product->id)
            ->assertJsonPath('data.shipping.city', 'Delhi')
            ->assertJsonPath('data.totals.total_label', '₹285.00');

        $this->postJson('/api/orders/track', ['number' => $number, 'email' => 'someone@example.com'])->assertNotFound();
        $this->postJson('/api/orders/track', ['number' => $number])->assertUnprocessable()->assertJsonValidationErrors('email');

        CMS::settings()->set('features.pricing_enabled', false);
        $this->postJson('/api/orders/track', ['number' => $number, 'email' => 'asha@example.com'])
            ->assertOk()->assertJsonMissingPath('data.totals')->assertJsonMissingPath('data.items.0.total_label');

        CMS::settings()->set('features.orders_enabled', false);
        $this->postJson('/api/orders/track', ['number' => $number, 'email' => 'asha@example.com'])->assertNotFound();
    }

    public function test_an_enquiry_is_sent_from_the_cart_only_while_enquiries_are_on(): void
    {
        $product = $this->product('Blue Tee', 100, 0);

        $this->postJson('/api/enquiries', ['name' => 'Asha', 'email' => 'asha@example.com'], $this->cartHeaders())->assertNotFound();

        CMS::settings()->set('features.orders_enabled', false);
        CMS::settings()->set('features.enquiry_enabled', true);

        // Stock only limits real orders, so an out-of-stock product can still be asked about.
        $this->postJson('/api/cart/items', ['product_id' => $product->id], $this->cartHeaders())->assertCreated();
        $this->postJson('/api/orders', $this->customer(), $this->cartHeaders())->assertNotFound();

        $number = $this->postJson('/api/enquiries', ['name' => 'Asha', 'email' => 'asha@example.com', 'message' => 'Price for 50?'], $this->cartHeaders())
            ->assertCreated()->json('data.number');

        $enquiry = Enquiry::where('enquiry_number', $number)->with('items')->firstOrFail();
        $this->assertSame('Blue Tee', $enquiry->items->first()->product_name);
        $this->assertSame('Price for 50?', $enquiry->notes);
    }

    public function test_the_host_replaces_an_api_controller_by_binding_a_subclass(): void
    {
        $this->app->bind(StoreController::class, fn () => new class extends StoreController
        {
            public function show(): JsonResponse
            {
                return response()->json(['data' => [...parent::show()->getData(true)['data'], 'support_phone' => '1800-000']]);
            }
        });

        $this->getJson('/api/store')->assertOk()->assertJsonPath('data.support_phone', '1800-000')->assertJsonPath('data.orders', true);
    }

    public function test_a_running_offer_shows_on_the_product_with_the_price_it_replaces(): void
    {
        $headphones = $this->product('Wireless Headphones', 2000);
        ProductOffer::create(['product_id' => $headphones->id, 'name' => 'Launch sale', 'discount_type' => 'percentage', 'value' => 15]);

        $this->getJson('/api/products?ids='.$headphones->id)
            ->assertJsonPath('data.0.offer_label', '15% off')
            ->assertJsonPath('data.0.offer_name', 'Launch sale')
            ->assertJsonPath('data.0.price', 1700)
            ->assertJsonPath('data.0.compare_price_label', '₹2,000.00');
    }
}
