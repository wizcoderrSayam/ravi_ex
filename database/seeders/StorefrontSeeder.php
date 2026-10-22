<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Category;
use Peer\CMS\Models\Form;
use Peer\CMS\Models\FormFieldRule;
use Peer\CMS\Models\Menu;
use Peer\CMS\Models\SettingGroup;

/**
 * Ravi Exclusive's storefront, converted from its old Blade storefront: the category tree its
 * pages and menus link to, the store's details (CMS → Settings → Custom Settings → "store"), the
 * header and footer menus, the "contact" form, and the storefront itself, which is CMS content:
 * one layout, reusable components and pages (their HTML and JS live in database/seeders/storefront).
 * The products, their pictures and the old orders come from `php artisan ecommerce:import-legacy`,
 * which files them under these same categories. Everything can be edited afterwards in the admin
 * panel and in GrapesJS.
 *
 * It can run again on a store that has it already: details, menus and the form the owner may
 * have changed since are left as they are, and the storefront's files are imported again.
 */
class StorefrontSeeder extends Seeder
{
    /**
     * The custom settings group the storefront reads the store's details from.
     */
    public const SETTINGS_GROUP = 'store';

    /**
     * The old store's categories in its order: top-level name => [subcategory name => the #hash
     * the old site linked it with (/girls#patiala-farshi-salwar)].
     *
     * @var array<string, array<string, string>>
     */
    private const CATEGORIES = [
        'Girls' => [
            'Lehenga' => 'lehenga',
            'Sharara / Palazzo' => 'sharara-palazzo',
            'Patiala Salwar Suits / Farshi Salwar' => 'patiala-farshi-salwar',
            'Frocks & Gowns' => 'frocks-gowns',
        ],
        'Boys' => [
            'Sherwani' => 'sherwani',
            'Indo-Westerns' => 'indo-westerns',
            'Coat Suits / Tuxedos' => 'coat-suits-tuxedos',
            'Jodhpuri' => 'jodhpuri',
            'Kurta Pajama' => 'kurta-pajama',
            'Waistcoat Sets' => 'waistcoat-sets',
        ],
        'Men' => [
            'Kurta Pajamas' => 'kurta-pajamas',
            'Waistcoat Sets' => 'waistcoat-sets',
            'Indo-Westerns' => 'indo-westerns',
        ],
        'Women' => [
            'Co-ord Sets' => 'co-ord-sets',
        ],
    ];

    /**
     * What the old pages showed about the store, read by the header, footer and pages from
     * /api/cms/settings/store. That API is public, so nothing secret belongs here.
     *
     * @var array<string, string>
     */
    private const DETAILS = [
        'welcome_text' => 'Welcome to Ravi Exclusive, Katra Jaimal Singh, Amritsar',
        'phone' => '+918148146262',
        'phone_display' => '+91-814-814-62-62',
        'whatsapp' => '918148146262',
        'whatsapp_message' => 'Hi, I visited your website and am interested in your products',
        'business_name' => 'M/s Ravi Exclusive',
        'address_line1' => '22, Katra Jaimal Singh',
        'address_line2' => 'Amritsar, Punjab, India – 143001',
        'maps_url' => 'https://www.google.com/maps/search/?api=1&query=22+Katra+Jaimal+Singh+Amritsar+143001',
        'instagram_url' => 'https://www.instagram.com/ravi.exclusif/',
        'instagram_handle' => 'ravi.exclusif',
        'footer_about' => 'Designer ethnic and occasion wear for the whole family, from the heart of Amritsar.',
    ];

    /**
     * The old footer's policy links, for the pages the old admin wrote: label => URL.
     *
     * @var array<string, string>
     */
    private const POLICIES = [
        'About Us' => '/about-us',
        'Contact Us' => '/contact-us',
        'Shipping Policy' => '/shipping-policy',
        'Returns & Exchanges' => '/returns-and-exchanges',
        'Privacy Policy' => '/privacy-policy',
        'Terms & Conditions' => '/terms-and-conditions',
    ];

    public function run(): void
    {
        $this->settings();
        $this->details();
        $this->categories();
        $this->menus();
        $this->contactForm();
        $this->storefront();
    }

    private function settings(): void
    {
        $settings = CMS::settings();
        $settings->set('general.app_name', 'Ravi Exclusive');

        // The old store took orders (sent on WhatsApp) and showed prices and stock; switch them in CMS → Settings → General → Features.
        foreach (['orders_enabled' => true, 'enquiry_enabled' => false, 'pricing_enabled' => true, 'stock_enabled' => true] as $flag => $enabled) {
            $settings->set("features.{$flag}", $enabled);
        }
    }

    private function details(): void
    {
        $group = SettingGroup::firstOrCreate(['name' => self::SETTINGS_GROUP]);

        foreach (self::DETAILS as $key => $value) {
            $group->fields()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Created parent by parent in the old order, which is the order /api/categories lists them in.
     */
    private function categories(): void
    {
        foreach (self::CATEGORIES as $name => $children) {
            $parent = Category::firstOrCreate(['name' => $name, 'parent_id' => null]);

            foreach (array_keys($children) as $child) {
                Category::firstOrCreate(['name' => $child, 'parent_id' => $parent->id]);
            }
        }
    }

    /**
     * The old navigation. Link URLs can name a store detail in braces ({whatsapp}), which the
     * storefront fills in from the "store" settings, so a new number needs no menu change.
     */
    private function menus(): void
    {
        $header = $this->newMenu('Header');
        $position = 0;

        foreach ($header ? self::CATEGORIES : [] as $name => $children) {
            $url = '/'.strtolower($name);
            $item = $header->items()->create(['label' => $name, 'url' => $url, 'sort_order' => $position++]);

            foreach (array_keys($children) as $index => $child) {
                $header->items()->create(['parent_id' => $item->id, 'label' => $child, 'url' => $url.'#'.$children[$child], 'sort_order' => $index]);
            }
        }

        $header?->items()->create(['label' => 'New Arrivals', 'url' => '/new-arrivals', 'sort_order' => $position]);

        $shop = $this->newMenu('Footer Shop');
        foreach (['Boys', 'Girls', 'Women', 'Men'] as $index => $name) {
            $shop?->items()->create(['label' => $name, 'url' => '/'.strtolower($name), 'sort_order' => $index]);
        }

        $help = $this->newMenu('Footer Help');
        $help?->items()->create(['label' => 'Visit the store', 'url' => '/#store', 'sort_order' => 0]);
        $help?->items()->create(['label' => 'Order on WhatsApp', 'url' => 'https://wa.me/{whatsapp}', 'target' => '_blank', 'sort_order' => 1]);
        $help?->items()->create(['label' => 'Instagram', 'url' => '{instagram_url}', 'target' => '_blank', 'sort_order' => 2]);

        $policies = $this->newMenu('Footer Policies');
        foreach (array_keys(self::POLICIES) as $index => $label) {
            $policies?->items()->create(['label' => $label, 'url' => self::POLICIES[$label], 'sort_order' => $index]);
        }
    }

    /**
     * A new, empty menu with this name, or null when the store has it already (its items may have
     * been edited since).
     */
    private function newMenu(string $name): ?Menu
    {
        $menu = Menu::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);

        return $menu->wasRecentlyCreated ? $menu : null;
    }

    /**
     * The form the contact page posts to (CMS → Forms), with the fields `ecommerce:import-legacy`
     * gives the old store's contact messages.
     */
    private function contactForm(): void
    {
        $form = Form::firstOrCreate(['name' => 'contact']);

        foreach ([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
        ] as $field => $rules) {
            FormFieldRule::firstOrCreate(['form_id' => $form->id, 'field' => $field], ['rules' => $rules]);
        }
    }

    /**
     * The layout, components, pages and media in database/seeders/storefront, loaded the way
     * CMS → Import Site loads a zip, without the backup (the database is new).
     */
    private function storefront(): void
    {
        CMS::importSite(__DIR__.'/storefront', backup: false);
    }
}
