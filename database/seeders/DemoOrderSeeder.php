<?php

namespace Database\Seeders;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Models\Address;
use Carepeerinfotech\Ecommerce\Models\Coupon;
use Carepeerinfotech\Ecommerce\Models\Order;
use Carepeerinfotech\Ecommerce\Models\Product;
use Carepeerinfotech\Ecommerce\Models\ProductVariant;
use Carepeerinfotech\Ecommerce\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo customers, coupons and orders so the admin dashboard, order list and
 * order pages have something to show. Never runs in production.
 *
 * Customers use @example.com addresses and the password "password". Orders are
 * numbered DEMO-1001 onwards so they are easy to spot and delete. Totals follow
 * the admin order page: subtotal − coupon + shipping + GST, using the shipping
 * and GST settings. Run after StorefrontCatalogSeeder. Safe to re-run.
 */
class DemoOrderSeeder extends Seeder
{
    public function __construct(private SettingsService $settings) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping demo orders in production.');

            return;
        }

        DB::transaction(function () {
            $customers = $this->seedCustomers();
            $coupons = $this->seedCoupons();

            foreach ($this->orders() as $index => $order) {
                $this->seedOrder($index, $order, $customers, $coupons);
            }
        });
    }

    /**
     * @return list<array{user: ?User, name: string, email: string, phone: string, address: string, city: string, state: string, postal_code: string}>
     */
    private function seedCustomers(): array
    {
        $customers = [];

        foreach ($this->customers() as $customer) {
            $user = null;

            if ($customer['registered']) {
                $user = User::updateOrCreate(['email' => $customer['email']], [
                    'name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'is_customer' => true,
                ]);
                $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

                Address::updateOrCreate(['user_id' => $user->id, 'label' => 'Home'], [
                    'name' => $customer['name'],
                    'phone' => $customer['phone'],
                    'email' => $customer['email'],
                    'address_line1' => $customer['address'],
                    'city' => $customer['city'],
                    'state' => $customer['state'],
                    'postal_code' => $customer['postal_code'],
                    'country' => 'IN',
                    'is_default_shipping' => true,
                    'is_default_billing' => true,
                ]);
            }

            $customers[] = ['user' => $user, ...$customer];
        }

        return $customers;
    }

    /**
     * @return array<string, Coupon>
     */
    private function seedCoupons(): array
    {
        $coupons = [
            'WELCOME10' => ['discount_type' => 'percentage', 'value' => 10],
            'SHAGUN500' => ['discount_type' => 'flat', 'value' => 500],
        ];

        return collect($coupons)->map(fn (array $coupon, string $code): Coupon => Coupon::updateOrCreate(['code' => $code], [
            ...$coupon,
            'is_active' => true,
            'starts_at' => now()->subMonths(3)->startOfMonth(),
            'ends_at' => null,
        ]))->all();
    }

    /**
     * @param  array{customer: int, days_ago: int, status: string, payment: 'cod'|'paytm', coupon: ?string, items: list<array{string, string, int}>}  $definition
     * @param  list<array{user: ?User, name: string, email: string, phone: string, address: string, city: string, state: string, postal_code: string}>  $customers
     * @param  array<string, Coupon>  $coupons
     */
    private function seedOrder(int $index, array $definition, array $customers, array $coupons): void
    {
        $customer = $customers[$definition['customer']];
        $placedAt = now()->subDays($definition['days_ago'])->startOfDay()->addHours(10 + $index % 9)->addMinutes(7 * $index % 60);
        $lines = array_map(fn (array $item): array => $this->line(...$item), $definition['items']);

        $subtotal = array_sum(array_map(fn (array $line): float => $line['total'], $lines));
        $coupon = $definition['coupon'] ? $coupons[$definition['coupon']] : null;
        $discount = match ($coupon?->discount_type) {
            'percentage' => round($subtotal * (float) $coupon->value / 100, 2),
            'flat' => min((float) $coupon->value, $subtotal),
            default => 0.0,
        };
        $taxable = $subtotal - $discount;
        $shipping = $this->shippingFor($taxable);
        $gstPercent = $this->settings->float('commerce', 'gst_percent', 18);
        $tax = round($taxable * $gstPercent / 100, 2);
        $total = round($taxable + $shipping + $tax, 2);

        $address = [
            'name' => $customer['name'],
            'address' => $customer['address'],
            'city' => $customer['city'],
            'state' => $customer['state'],
            'postal_code' => $customer['postal_code'],
            'country' => 'IN',
        ];

        $order = Order::updateOrCreate(['order_number' => 'DEMO-'.(1001 + $index)], [
            'user_id' => $customer['user']?->id,
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'billing_name' => $address['name'],
            'billing_email' => $customer['email'],
            'billing_phone' => $customer['phone'],
            'billing_address' => $address['address'],
            'billing_city' => $address['city'],
            'billing_state' => $address['state'],
            'billing_postal_code' => $address['postal_code'],
            'billing_country' => $address['country'],
            'billing_same_as_shipping' => true,
            'shipping_name' => $address['name'],
            'shipping_address' => $address['address'],
            'shipping_city' => $address['city'],
            'shipping_state' => $address['state'],
            'shipping_postal_code' => $address['postal_code'],
            'shipping_country' => $address['country'],
            'subtotal' => $subtotal,
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'coupon_discount' => $discount,
            'shipping_amount' => $shipping,
            'tax_percent' => $gstPercent,
            'tax_amount' => $tax,
            'total' => $total,
            'currency' => 'INR',
            'payment_method' => $definition['payment'],
            'payment_status' => $this->paymentStatus($definition['status'], $definition['payment']),
            'status' => $definition['status'],
            'estimated_delivery_days' => 5,
            'courier_name' => in_array($definition['status'], ['shipped', 'delivered'], true) ? $this->courierFor($index) : null,
            'shipping_weight' => array_sum(array_map(fn (array $line): float => $line['weight'] * $line['quantity'], $lines)),
        ]);

        $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->saveQuietly();

        $order->items()->delete();
        foreach ($lines as $line) {
            $order->items()->create($line);
        }

        $this->seedPayment($order, $placedAt);
        $this->seedShipment($order, $index, $placedAt);
    }

    /**
     * @return array{product_id: int, product_variant_id: int, product_name: string, product_sku: string, variant_label: string, variant_options: array<string, string>, quantity: int, price: float, total: float, weight: float}
     */
    private function line(string $productSlug, string $size, int $quantity): array
    {
        $product = Product::where('slug', $productSlug)->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->where('option_label', $size)->firstOrFail();

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'product_sku' => $variant->sku,
            'variant_label' => $variant->option_label,
            'variant_options' => $variant->option_values,
            'quantity' => $quantity,
            'price' => (float) $variant->price,
            'total' => (float) $variant->price * $quantity,
            'weight' => $variant->shippingWeight(),
        ];
    }

    private function shippingFor(float $amount): float
    {
        if (! $this->settings->bool('shipping', 'charges_enabled', true)) {
            return 0.0;
        }

        return $amount >= $this->settings->float('shipping', 'free_above', 999) ? 0.0 : $this->settings->float('shipping', 'flat_rate', 49);
    }

    /** Cash on delivery is collected at the door; online payments are captured at checkout. */
    private function paymentStatus(string $status, string $method): string
    {
        return match (true) {
            $method === 'cod' => $status === 'delivered' ? 'paid' : 'pending',
            $status === 'cancelled' => 'failed',
            $status === 'pending' => 'pending',
            default => 'paid',
        };
    }

    private function seedPayment(Order $order, Carbon $placedAt): void
    {
        $order->payments()->delete();

        if ($order->payment_method !== 'paytm') {
            return;
        }

        $order->payments()->create([
            'gateway' => 'paytm',
            'transaction_id' => 'DEMOTXN'.substr($order->order_number, 5),
            'gateway_order_id' => $order->order_number,
            'amount' => $order->total,
            'currency' => 'INR',
            'status' => $order->payment_status,
            'paid_at' => $order->payment_status === 'paid' ? $placedAt->copy()->addMinutes(2) : null,
        ]);
    }

    private function seedShipment(Order $order, int $index, Carbon $placedAt): void
    {
        $order->shipments()->delete();

        if (! in_array($order->status, ['shipped', 'delivered'], true)) {
            return;
        }

        $delivered = $order->status === 'delivered';

        $order->shipments()->create([
            'provider' => 'shiprocket',
            'provider_order_id' => 'DEMO-SR-'.(1001 + $index),
            'shipment_id' => 'DEMO-SH-'.(1001 + $index),
            'awb_code' => '99000'.str_pad((string) (1001 + $index), 7, '0', STR_PAD_LEFT),
            'courier_name' => $order->courier_name,
            'status' => $delivered ? 'delivered' : 'in_transit',
            'freight_charge' => 85 + 10 * ($index % 4),
            'etd_days' => 5,
            'picked_up_at' => $placedAt->copy()->addDays(2),
            'delivered_at' => $delivered ? $placedAt->copy()->addDays(6) : null,
        ]);
    }

    private function courierFor(int $index): string
    {
        return ['Delhivery Surface', 'Blue Dart Air', 'DTDC Surface'][$index % 3];
    }

    /**
     * Guests (registered = false) check out without an account.
     *
     * @return list<array{registered: bool, name: string, email: string, phone: string, address: string, city: string, state: string, postal_code: string}>
     */
    private function customers(): array
    {
        return [
            ['registered' => true, 'name' => 'Harpreet Kaur', 'email' => 'harpreet.kaur@example.com', 'phone' => '9000000101', 'address' => '14, Ranjit Avenue, B Block', 'city' => 'Amritsar', 'state' => 'Punjab', 'postal_code' => '143001'],
            ['registered' => true, 'name' => 'Gurpreet Singh Sandhu', 'email' => 'gurpreet.sandhu@example.com', 'phone' => '9000000102', 'address' => '221, Model Town Extension', 'city' => 'Ludhiana', 'state' => 'Punjab', 'postal_code' => '141002'],
            ['registered' => true, 'name' => 'Simran Bedi', 'email' => 'simran.bedi@example.com', 'phone' => '9000000103', 'address' => '7, Green Park, Near Ravidas Chowk', 'city' => 'Jalandhar', 'state' => 'Punjab', 'postal_code' => '144001'],
            ['registered' => true, 'name' => 'Manjot Singh Gill', 'email' => 'manjot.gill@example.com', 'phone' => '9000000104', 'address' => 'House 1186, Sector 18-C', 'city' => 'Chandigarh', 'state' => 'Chandigarh', 'postal_code' => '160018'],
            ['registered' => false, 'name' => 'Kiran Arora', 'email' => 'kiran.arora@example.com', 'phone' => '9000000105', 'address' => 'C-42, Rajouri Garden', 'city' => 'New Delhi', 'state' => 'Delhi', 'postal_code' => '110027'],
            ['registered' => true, 'name' => 'Navneet Kaur Dhillon', 'email' => 'navneet.dhillon@example.com', 'phone' => '9000000106', 'address' => '38, Urban Estate Phase 2', 'city' => 'Patiala', 'state' => 'Punjab', 'postal_code' => '147002'],
            ['registered' => true, 'name' => 'Arjun Mehra', 'email' => 'arjun.mehra@example.com', 'phone' => '9000000107', 'address' => 'Flat 502, Tower B, DLF Phase 4', 'city' => 'Gurugram', 'state' => 'Haryana', 'postal_code' => '122009'],
            ['registered' => true, 'name' => 'Rupinder Kaur Grewal', 'email' => 'rupinder.grewal@example.com', 'phone' => '9000000108', 'address' => '19, Ajit Road, Street 4', 'city' => 'Bathinda', 'state' => 'Punjab', 'postal_code' => '151001'],
            ['registered' => true, 'name' => 'Aman Sharma', 'email' => 'aman.sharma@example.com', 'phone' => '9000000109', 'address' => '56, Dalhousie Road', 'city' => 'Pathankot', 'state' => 'Punjab', 'postal_code' => '145001'],
            ['registered' => false, 'name' => 'Mehak Chawla', 'email' => 'mehak.chawla@example.com', 'phone' => '9000000110', 'address' => '102, Sarabha Nagar', 'city' => 'Ludhiana', 'state' => 'Punjab', 'postal_code' => '141001'],
        ];
    }

    /**
     * Items are [product slug, size, quantity]; customer is an index into customers().
     *
     * @return list<array{customer: int, days_ago: int, status: string, payment: 'cod'|'paytm', coupon: ?string, items: list<array{string, string, int}>}>
     */
    private function orders(): array
    {
        return [
            ['customer' => 0, 'days_ago' => 58, 'status' => 'delivered', 'payment' => 'paytm', 'coupon' => null, 'items' => [['women-lehnga-set', 'M', 1]]],
            ['customer' => 1, 'days_ago' => 55, 'status' => 'delivered', 'payment' => 'cod', 'coupon' => null, 'items' => [['pure-silk-sherwani', 'L', 1], ['velvet-safa', 'Free Size', 1], ['heritage-punjabi-jutti', 'Free Size', 1]]],
            ['customer' => 2, 'days_ago' => 51, 'status' => 'delivered', 'payment' => 'paytm', 'coupon' => 'WELCOME10', 'items' => [['sharara-suit', 'S', 1], ['heritage-kurti', 'S', 1]]],
            ['customer' => 3, 'days_ago' => 47, 'status' => 'delivered', 'payment' => 'cod', 'coupon' => null, 'items' => [['festive-sherwani', 'XL', 1]]],
            ['customer' => 4, 'days_ago' => 44, 'status' => 'delivered', 'payment' => 'paytm', 'coupon' => null, 'items' => [['rosette-tiered-party-frock', '6Y', 2]]],
            ['customer' => 5, 'days_ago' => 40, 'status' => 'cancelled', 'payment' => 'paytm', 'coupon' => null, 'items' => [['chikankari-lehenga', 'M', 1]]],
            ['customer' => 6, 'days_ago' => 36, 'status' => 'delivered', 'payment' => 'paytm', 'coupon' => 'SHAGUN500', 'items' => [['chikankari-jodhpuri-suit', 'L', 1]]],
            ['customer' => 6, 'days_ago' => 33, 'status' => 'cancelled', 'payment' => 'cod', 'coupon' => null, 'items' => [['festive-trouser', 'XL', 1]]],
            ['customer' => 7, 'days_ago' => 31, 'status' => 'delivered', 'payment' => 'cod', 'coupon' => null, 'items' => [['velvet-punjabi-suit', 'L', 1], ['chikankari-jewellery-set', 'Free Size', 1]]],
            ['customer' => 8, 'days_ago' => 26, 'status' => 'shipped', 'payment' => 'paytm', 'coupon' => null, 'items' => [['banarasi-pathani-suit', 'M', 1], ['regal-nehru-jacket', 'M', 1]]],
            ['customer' => 0, 'days_ago' => 20, 'status' => 'shipped', 'payment' => 'cod', 'coupon' => null, 'items' => [['festive-co-ord-set', 'M', 1]]],
            ['customer' => 9, 'days_ago' => 15, 'status' => 'shipped', 'payment' => 'paytm', 'coupon' => 'WELCOME10', 'items' => [['heritage-anarkali', 'S', 1], ['chikankari-potli', 'Free Size', 1]]],
            ['customer' => 1, 'days_ago' => 10, 'status' => 'processing', 'payment' => 'paytm', 'coupon' => null, 'items' => [['ivory-waistcoat-party-set', '8Y', 1]]],
            ['customer' => 2, 'days_ago' => 6, 'status' => 'processing', 'payment' => 'paytm', 'coupon' => null, 'items' => [['velvet-saree', 'M', 1]]],
            ['customer' => 3, 'days_ago' => 4, 'status' => 'processing', 'payment' => 'cod', 'coupon' => null, 'items' => [['mirror-work-shirt', 'XL', 2]]],
            ['customer' => 7, 'days_ago' => 2, 'status' => 'pending', 'payment' => 'paytm', 'coupon' => null, 'items' => [['regal-bridal-lehenga', 'L', 1]]],
            ['customer' => 8, 'days_ago' => 1, 'status' => 'pending', 'payment' => 'cod', 'coupon' => null, 'items' => [['olive-denim-jacket', 'L', 1]]],
        ];
    }
}
