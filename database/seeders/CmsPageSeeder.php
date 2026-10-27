<?php

namespace Database\Seeders;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Models\CmsPage;
use Carepeerinfotech\Ecommerce\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Store information pages. About and Contact only restate what the demo site
 * already says, so they are published. The policy pages commit the business to
 * timelines and terms, so they are seeded as drafts for the owner to review and
 * publish from the admin. Safe to re-run: an existing page is never overwritten,
 * so edits made in the admin survive.
 */
class CmsPageSeeder extends Seeder
{
    private const ADDRESS = 'M/s Ravi Exclusive,  22, Katra Jaimal Singh, Amritsar, Amritsar, Punjab, India – 143001';

    private const PHONE = '+91-814-814-62-62';

    private const WHATSAPP_URL = 'https://wa.me/918148146262';

    public function __construct(private SettingsService $settings) {}

    public function run(): void
    {
        $authorId = User::query()->whereHas('roles', fn ($roles) => $roles->where('slug', 'super-admin'))->value('id');

        foreach ($this->pages() as $slug => $page) {
            CmsPage::firstOrCreate(['slug' => $slug], [
                ...$page,
                'created_by' => $authorId,
                'published_at' => $page['status'] === 'published' ? now() : null,
            ]);
        }
    }

    /**
     * @return array<string, array{title: string, status: 'draft'|'published', meta_title: string, meta_description: string, content: string}>
     */
    private function pages(): array
    {
        $address = self::ADDRESS;
        $phone = self::PHONE;
        $whatsapp = self::WHATSAPP_URL;
        $flatRate = number_format($this->settings->float('shipping', 'flat_rate', 49));
        $freeAbove = number_format($this->settings->float('shipping', 'free_above', 999));

        return [
            'about-us' => [
                'title' => 'About Us',
                'status' => 'published',
                'meta_title' => 'About Ravi Exclusive | Ethnic Wear Store in Katra Jaimal Singh, Amritsar',
                'meta_description' => 'Ravi Exclusive brings designer ethnic and occasion wear for the whole family from Katra Jaimal Singh, one of Amritsar\'s busiest clothing bazaars.',
                'content' => <<<HTML
                    <h2>Amritsar da andaaz</h2>
                    <p>Ravi Exclusive sits in Katra Jaimal Singh, one of Amritsar's busiest clothing bazaars. We bring that bazaar energy home: bright Punjabi suits for Lohri and Visakhi, karva chauth lehengas, sherwanis and pagg for the groom, and festive pieces for every rasam.</p>
                    <h2>For the whole parivaar</h2>
                    <p>From bridal lehengas and Punjabi suits to sherwanis, kurta pyjamas, kids' festive wear, safas and juttis, we hand-pick designer ethnic and occasion wear for everyone in the family.</p>
                    <h2>The Punjabi wedding edit</h2>
                    <p>From the mehndi night to the doli, we dress the bride, the groom and the whole baraat: party lehengas and anarkalis for mehndi and sangeet, Punjabi suits for shagan and chunni, bridal lehengas for the bride, and sherwanis, safas and juttis for the groom.</p>
                    <h2>Aao ji, visit us</h2>
                    <p>Come to our store for personal styling, trials and fittings for the whole family.</p>
                    <p>{$address}<br>Phone / WhatsApp: <a href="tel:+918148146262">{$phone}</a></p>
                    HTML,
            ],
            'contact-us' => [
                'title' => 'Contact Us',
                'status' => 'published',
                'meta_title' => 'Contact Ravi Exclusive | Katra Jaimal Singh, Amritsar',
                'meta_description' => "Visit Ravi Exclusive at  22, Katra Jaimal Singh, Amritsar, Amritsar, or call and WhatsApp us on {$phone}.",
                'content' => <<<HTML
                    <h2>Visit the store</h2>
                    <p>{$address}</p>
                    <p><a href="https://www.google.com/maps/search/?api=1&amp;query=22+Katra+Jaimal+Singh+Amritsar+143001" target="_blank" rel="noopener">Get directions on Google Maps</a></p>
                    <h2>Call or WhatsApp</h2>
                    <p>Phone: <a href="tel:+918148146262">{$phone}</a></p>
                    <p><a href="{$whatsapp}" target="_blank" rel="noopener">Chat with us on WhatsApp</a> for orders, sizing and alteration questions.</p>
                    <h2>Instagram</h2>
                    <p>See the newest arrivals first: <a href="https://www.instagram.com/ravi.exclusif/" target="_blank" rel="noopener">@ravi.exclusif</a></p>
                    HTML,
            ],
            'shipping-policy' => [
                'title' => 'Shipping Policy',
                'status' => 'draft',
                'meta_title' => 'Shipping Policy | Ravi Exclusive',
                'meta_description' => "Shipping charges, dispatch times and delivery across India for orders from Ravi Exclusive, Amritsar. Free shipping on orders above ₹{$freeAbove}.",
                'content' => <<<HTML
                    <h2>Shipping charges</h2>
                    <p>Orders above ₹{$freeAbove} ship free within India. A flat shipping charge of ₹{$flatRate} applies to smaller orders.</p>
                    <h2>Dispatch</h2>
                    <p>Ready-to-ship orders are dispatched from our Amritsar store within 2–4 working days. Outfits that need alterations or fitting changes are dispatched once the alterations are done; we will confirm the timeline with you on WhatsApp.</p>
                    <h2>Delivery</h2>
                    <p>We ship across India through our courier partners. Delivery usually takes 3–7 working days after dispatch, depending on your location. You will receive tracking details once your order is shipped.</p>
                    <h2>Cash on delivery</h2>
                    <p>Cash on delivery is available on eligible orders and pin codes.</p>
                    <h2>Questions</h2>
                    <p>Call or WhatsApp us on <a href="tel:+918148146262">{$phone}</a>.</p>
                    HTML,
            ],
            'returns-and-exchanges' => [
                'title' => 'Returns & Exchanges',
                'status' => 'draft',
                'meta_title' => 'Returns & Exchanges | Ravi Exclusive',
                'meta_description' => 'How to request a return or size exchange for an order from Ravi Exclusive, Amritsar.',
                'content' => <<<HTML
                    <h2>Size exchanges</h2>
                    <p>If something does not fit, contact us within 7 days of delivery for a size exchange, subject to stock. Items must be unworn, unwashed and returned with all tags and original packaging.</p>
                    <h2>Returns</h2>
                    <p>Returns are accepted within 7 days of delivery for items that arrive damaged or different from what you ordered. Please share photos of the item and packaging on WhatsApp so we can resolve it quickly.</p>
                    <h2>Items we cannot take back</h2>
                    <ul>
                        <li>Outfits that have been altered or custom-fitted</li>
                        <li>Bridal lehengas and made-to-order pieces</li>
                        <li>Jewellery and accessories, for hygiene reasons</li>
                        <li>Items bought on final sale</li>
                    </ul>
                    <h2>Refunds</h2>
                    <p>Approved refunds are made to the original payment method within 7–10 working days of us receiving the item. Cash on delivery orders are refunded by bank transfer.</p>
                    <h2>Start a return or exchange</h2>
                    <p>WhatsApp us on <a href="{$whatsapp}" target="_blank" rel="noopener">{$phone}</a> with your order number.</p>
                    HTML,
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'status' => 'draft',
                'meta_title' => 'Privacy Policy | Ravi Exclusive',
                'meta_description' => 'How Ravi Exclusive collects, uses and protects your personal information.',
                'content' => <<<HTML
                    <h2>What we collect</h2>
                    <p>When you place an order or contact us, we collect your name, phone number, email address, and billing and shipping addresses. Payments are processed by our payment partner; we do not store your card or bank details.</p>
                    <h2>How we use it</h2>
                    <ul>
                        <li>To process, ship and support your orders</li>
                        <li>To send order, payment and shipping updates by email, SMS or WhatsApp</li>
                        <li>To answer your questions and requests</li>
                        <li>To send offers and new arrivals, only if you subscribe; you can unsubscribe at any time</li>
                    </ul>
                    <h2>Who we share it with</h2>
                    <p>We share only what is needed with our payment gateway and courier partners to complete your order. We do not sell your personal information.</p>
                    <h2>Your choices</h2>
                    <p>You can ask us to update or delete your personal information by contacting us on <a href="tel:+918148146262">{$phone}</a>.</p>
                    <h2>Contact</h2>
                    <p>{$address}</p>
                    HTML,
            ],
            'terms-and-conditions' => [
                'title' => 'Terms & Conditions',
                'status' => 'draft',
                'meta_title' => 'Terms & Conditions | Ravi Exclusive',
                'meta_description' => 'Terms for shopping with Ravi Exclusive online and in store.',
                'content' => <<<HTML
                    <h2>About these terms</h2>
                    <p>These terms apply to orders placed with Ravi Exclusive ({$address}) through this website, WhatsApp or our store.</p>
                    <h2>Products and pricing</h2>
                    <p>Prices are in Indian Rupees. Colours may look slightly different on screen, and handcrafted and embroidered pieces can vary slightly from the photos. We may correct pricing or stock errors and will contact you before processing an affected order.</p>
                    <h2>Orders and payment</h2>
                    <p>An order is confirmed once payment is received, or once a cash on delivery order is accepted. We may cancel an order if an item is unavailable, and will refund any amount paid.</p>
                    <h2>Shipping, returns and exchanges</h2>
                    <p>Please see our Shipping Policy and Returns &amp; Exchanges pages.</p>
                    <h2>Governing law</h2>
                    <p>These terms are governed by the laws of India, and disputes are subject to the jurisdiction of the courts at Amritsar, Punjab.</p>
                    HTML,
            ],
        ];
    }
}
