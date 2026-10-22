# Grapes Store

A demo store that shows a GrapesJS-built storefront and an ecommerce admin panel working together on one Laravel 13 app (PHP 8.3+).

- **CMS** ([`carepeerinfotech/cms`](https://github.com/carepeerinfotech/cms)): the panel at `/cms` with the GrapesJS page editor, layouts, reusable components, media library, menus, forms, categories, backups and settings. It also serves the public site.
- **Ecommerce admin** ([`carepeerinfotech/ecommerce`](https://github.com/carepeerinfotech/ecommerce)): products, orders, enquiries, coupons, users and roles, inside the same panel.
- **This app**: the storefront. It adds the shop API (`/api/...`), the cart, checkout, and a seeded site (layout, components and pages) that is plain CMS content, so everything stays editable in GrapesJS.

## Setup

The two packages are separate private repositories, included here as git submodules in `packages/`. You need SSH access to both. The app runs whatever commit each submodule has checked out: Composer doesn't install them, it only autoloads their namespaces from `packages/`.

```bash
git clone --recurse-submodules <this repo> grapes-ecom && cd grapes-ecom
# already cloned without it: git submodule update --init

composer install
cp .env.example .env && php artisan key:generate
```

The submodules follow the branches `ecommerce-integration` (cms) and `cms-integration` (ecommerce). To work on a package, `cd packages/cms`, commit and push there, then commit the updated `packages/cms` pointer in this repo.

Because Composer doesn't install the packages, this app does what package discovery would:
- `bootstrap/providers.php` registers `CMSServiceProvider` and `EcommerceServiceProvider`, and `config/app.php` adds the `CMS` facade alias.
- `composer.json` requires the packages' own dependencies (`phpoffice/phpspreadsheet`). When a package adds one to its `composer.json`, add it here too.

The submodules are at the releases cms `v2.4.0` and ecommerce `v1.0.0`. The panel's header shows both (`CMS v2.4.0`, `Ecommerce v1.0.0`) and the editors only the CMS's: each package sets its release in its own code, so it shows without Composer.

Edit `.env`:

- **Database:** use **MySQL** (`DB_CONNECTION=mysql`, plus host, name and credentials). CMS backups use `mysqldump`, and importing a site backs up first, so import won't work on SQLite.
- **Demo admin:** set `ECOMMERCE_ADMIN_EMAIL` and `ECOMMERCE_ADMIN_PASSWORD`. The seeder creates a super admin from them and does nothing if they are empty.
- **Media:** uploads and the seeded product pictures go to `public/media` (ignored by git).

Then create the schema and demo data:

```bash
php artisan migrate:fresh --seed
```

`composer dev` runs the dev server. Mail (enquiry emails) is queued, so run `php artisan queue:work --stop-when-empty` to send it.

| Where | URL |
| --- | --- |
| Storefront | `/` (served by the CMS) |
| Admin login | `/admin/login` |
| CMS panel (pages, GrapesJS editor, media) | `/cms` |
| Ecommerce admin | `/admin` |
| Search everything (records, page and component code, settings) | ⌘K / Ctrl+K in the panel header, or `/cms/search` |

The seeder loads 12 products with SVG pictures in the media library, four categories (with sub-categories), a Primary and a Footer menu, a `contact` form, and the storefront.

## Feature flags

**CMS → Settings → General → Features** switches what the storefront does. The pages read them from `GET /api/store`, so a change shows on the next page load:

| Flag | Effect |
| --- | --- |
| Orders | Cart checks out as a cash-on-delivery order. Orders menu appears in the admin. |
| Enquiries | Cart is sent as an enquiry (no payment). Enquiries menu appears in the admin. |
| Show prices | Prices, offers and totals are rendered, and left out of the API when off. |
| Show stock | Stock counts and in/out-of-stock states are rendered, and left out of the API when off. |

With both Orders and Enquiries on, checkout offers a choice. With neither, the shop is a browse-only catalogue and the cart disappears.

## Storefront

Everything on the public site is CMS content you can edit in `/cms`:

- **Layout:** header, `{{content}}`, cart drawer, footer. Tailwind comes from the CDN, so there is no front-end build.
- **Components** (CMS → Components): `site-header` (menu with nested sub-menus from CMS → Site Menus), `cart-drawer`, `site-footer`, `hero-banner`, `category-list`, `product-grid`, `product-detail`, `checkout-form`, `contact-form`. Their JS uses `window.Store`, defined in the layout JS.
- **Pages:** Home, Shop, About, Contact Us, Checkout, and a dynamic page `product/{id}`. A page with a route pattern gets the URL values as `window.CMS_PARAMS`; the product page calls `/api/products?ids={id}`.
- **Menus:** the header and footer read `/api/cms/menus/primary` and `/footer`, so sub-menus are edited in CMS → Site Menus.

## Shop API

JSON, under `/api`. The CMS's own API (menus, blogs, settings, form submissions) is under `/api/cms`.

| Endpoint | Purpose |
| --- | --- |
| `GET /api/store` | Feature flags, labels, currency symbol |
| `GET /api/products` | Paginated products. Filters: `ids`, `search`, `category` (slug, includes sub-categories), `featured`, `sort` (`latest`, `oldest`, `price_asc`, `price_desc`, `name`), `per_page` |
| `GET /api/categories` | Category tree, in the order the categories were created. Each has an `image`: the picture named after its slug in the media folder `Categories` (`Categories/shirts.jpg` for "Shirts"), or null |
| `GET /api/cart`, `POST /api/cart/items`, `PATCH /api/cart/items/{id}`, `DELETE /api/cart/items/{id}` | Cart. Send an `X-Cart-Token` header (16 to 64 letters, digits or hyphens); the browser keeps one in `localStorage`. |
| `POST /api/orders` | Place a cash-on-delivery order from the cart (Orders flag). Billing fields `name`, `email`, `phone`, `address`, `city`, `state`, `postal_code`, `country`; with `ship_to_different_address` the same fields prefixed `shipping_` |
| `POST /api/enquiries` | Send the cart as an enquiry (Enquiries flag) |
| `POST /api/orders/track` | Look up an order by `number` and the `email` it was placed with (Orders flag). Amounts follow the Show prices flag |

Stock only limits real orders, so an out-of-stock product can still be added to an enquiry.

## Keeping the site in git: import and export

The seeded storefront is the folder `database/seeders/storefront`, in the format CMS → **Import / Export Site** reads:

```
layout.html layout.css layout.js
components/{slug}/content.html  script.js  styles.css  component.json
pages/{slug}.html  pages/{slug}.css
pages.json                      (every page's name, title, route_pattern, is_home, status, seo_head)
media/{folder}/{file}
```

Pages and the layout place components with `<x-slug prop="value" />` tags.

- **Import:** in CMS → Import / Export Site, pick a zip from the media library. The whole zip is checked first, then the database is backed up (CMS → Backups), then everything is imported. If the backup fails (a full disk, no `mysqldump`), nothing is imported. Components and pages with an existing slug are overwritten; nothing is deleted. Media files are not part of the backup.
- **Export:** download the current layout, components, pages and media as a zip, or write a folder with `CMS::exportSite('/path', media: true)`. The folder must be empty or an earlier export. Blog posts, menus, forms and settings are not exported.
- **From code:** `CMS::importSite($zipOrFolder, backup: true)`. The seeder uses it with `backup: false` because the database is new.

## Adding your own pages to the panel

A screen of this app's own (tours, leads, …) can live in the panel at `/cms`, with its sidebar and header around it.

**1. Route** (`routes/web.php`). `Route::cmsPanel()` adds the panel's `/cms` prefix, its middleware and the `accessCms` gate:

```php
Route::cmsPanel()->name('tours.')->group(function () {
    Route::get('/tours', [TourController::class, 'index'])->name('index'); // /cms/tours
});
```

**2. View**, extending the panel's layout:

```blade
@extends('cms::panel.layout')

@section('title', 'Tours')

@section('content')
    <h1 class="text-2xl font-semibold">Tours</h1>
@endsection
```

**3. Sidebar item**, in `config/cms.php`. It's the CMS's own config, so this app doesn't depend on the ecommerce package for it:

```php
'panel' => [
    'navigation' => [
        'tours' => ['label' => 'Tours', 'icon' => 'fa-map-o', 'route' => 'tours.index', 'active' => 'tours.*', 'sort' => 120],
        'leads' => ['label' => 'Leads', 'icon' => 'fa-address-book-o', 'sort' => 130, 'children' => [
            'all' => ['label' => 'All leads', 'route' => 'leads.index', 'active' => 'leads.*'],
        ]],
        'store.reviews' => ['label' => 'Reviews', 'route' => 'reviews.index'], // Store → Reviews
    ],
],
```

To add items from code instead (a module's own service provider), append to the same config in `boot()`:

```php
config(['cms.panel.navigation' => [
    ...config('cms.panel.navigation', []),
    'tours' => ['label' => 'Tours', 'icon' => 'fa-map-o', 'route' => 'tours.index', 'sort' => 120],
]]);
```

- **`sort`:** the sidebar runs Dashboard 0, Store 100, CMS 200, Users 750, Backups 800, Docs 850, Settings 900.
- **`active`:** a route name pattern that highlights the item.
- **`icon`:** a Font Awesome 4.7 name.
- **`url`:** use it instead of `route` for a plain link.
- **Nesting:** `children` can have `children` of their own, at any depth.
- **Adding to an existing menu:** key the item by the menu's id, a child after its parent's: `'store.reviews'` goes under Store, `'cms.forms.export'` under CMS → Forms. It goes after the menu's own items, and is left out while its parent is hidden or switched off (Orders, Enquiries).
- **Replacing an item:** reuse its id (`store`, `store.products`, `people`, `dashboard`, `backups`, …). The whole item is replaced, children included.
- **Hiding a CMS item:** `'panel' => ['hidden_navigation' => ['cms.categories']]` in `config/cms.php`. Its page still opens by URL.

More in the CMS's [your own pages](packages/cms/docs/host-pages.md) doc.

## Tests

```bash
composer test
```

Feature tests in `tests/Feature` cover the shop API, cart, checkout, feature flags and the seeded pages. Package tests run from each package (`cd packages/cms && vendor/bin/phpunit`).

## Layout of this repo

| Path | What |
| --- | --- |
| `app/Http/Controllers/Api` | Shop API controllers |
| `app/Http/Resources/ProductResource.php` | Product JSON, honouring the price and stock flags |
| `app/Models` | `Cart`, `CartItem`, `User` (extends the package user) |
| `routes/api.php` | Shop API routes |
| `database/seeders` | `StorefrontSeeder` (demo data) and the `storefront/` site folder |
| `config/cms.php` | Panel branding and the shared login |
| `packages/` | The CMS and ecommerce packages, as git submodules |
