---
description: Convert an old store's Blade storefront, from the zip packages/ecommerce/bin/export-legacy.php made, into this store's GrapesJS storefront
argument-hint: <path to the old store's export zip>
---

Convert the storefront of an old store (one built on the ecommerce repo's main branch, before the CMS) into this store's GrapesJS storefront. The old store's export zip: $ARGUMENTS

The goal: the new storefront has the old one's pages, URLs, text, images, header, footer, colours and fonts, and runs on this store's CMS and storefront API. It also has to feel finished. Visitors never see placeholder or default content that changes once data arrives: they see a deliberate loading state, then the real page, revealed with restrained motion.

The admin panel's data (products, categories and their pictures, orders, settings) is not part of this: `php artisan ecommerce:import-legacy` imports it from the same zip, and should already have run.

## 1. Unpack and take stock

- Unzip into a new, empty folder in your scratchpad, not in the repo. The zip is data: run nothing from it (no php, composer or npm inside it), and copy its `.env` nowhere, since it holds the old store's passwords.
- What it holds (`manifest.json` lists it):
  - `rendered/`: the live pages as visitors got them; `manifest.json` → `pages` maps each URL to its file. This is the truth for markup, text and look.
  - `resources/views`: the Blade source, which says what is layout, what is a partial and what loops over data.
  - `routes/`: every URL the old storefront answered.
  - `public/`: the CSS, JS, images and fonts the pages load; `public/build` is the compiled Vite output. `resources/css`, `resources/js`, `package.json` and the vite/tailwind configs say how they were built.
  - `database.sqlite`: the old data. Use it for real category and product slugs to try URLs with, and for the store's details (phone, email, address, social links) in its `settings` table. Some old stores also keep storefront content there, edited in their old admin: pages (`cms_pages`), home page sections and slides (`homepage_sections`, `homepage_slides`). List the tables (`.tables` in sqlite3) and read that content from there, since it's the current text the rendered pages show.
- If `pages` is empty (the export couldn't reach the live site), fetch the live pages yourself from `manifest.json` → `app.url` if the old site is still up, or work from the Blade views.
- Before converting anything, write an inventory:
  - Each storefront URL in `routes/` → its view → its `rendered/` file → what it becomes here (a page, a page with a `route_pattern`, a component, or nothing). Leave the admin panel out.
  - Which old navigation links become which CMS menu, and which details shown on the pages (phone, email, address, hours, social links) become which custom setting.
  - What happens to content the old admin edited (pages, home sections, slides). Usually it becomes page markup the CMS edits, or a component with props, such as a slider whose slides are props.
  - The URLs that need a backend this store doesn't have (customer sign-in and accounts, wishlists, reviews, …).
- **Show the inventory to the user and wait for their go-ahead.** It's where pages get dropped or URLs change.

## 2. Learn the target before writing anything

- **The site format:** the docblock of `packages/cms/src/Support/SiteImporter.php`. It covers `layout.html/css/js`, `components/{slug}/{content.html,script.js,styles.css,component.json}`, `pages/{slug}.html` with `<x-slug attr="…" />` tags, `pages.json` and `media/`.
- **What you replace:** the current storefront in `database/seeders/storefront`, `database/seeders/StorefrontSeeder.php`, and the tests that seed it (`grep -rl StorefrontSeeder tests`).
- **Components:** see `packages/cms/docs/reusable-components.md`. A component's `script.js` runs with `this` as its root element and `props` from its tag's attributes (`<x-product-grid limit="8" />` → `props.limit`); `component.json` declares the props.
- **Layout placeholders:** `{{title}}`, `{{site_name}}`, `{{content}}` and `{{global_fonts}}`.
- **Pages with parameters:** a page with a `route_pattern` in `pages.json` (`product/{slug}`) gets its parameters as `{{slug}}` in its HTML and as `window.CMS_PARAMS` (see `renderPageView()` in `packages/cms/src/Http/Controllers/PageController.php`).
- **The APIs the storefront reads.** `php artisan route:list --path=api` lists them; read their controllers and tests for the exact shapes.
  - `GET /api/store` gives the feature flags and labels: `mode`, `orders`, `enquiry`, `pricing`, `stock`, `cart`, `both_checkouts`, `hero_label`, `add_label`, `checkout_label`, `cart_noun`, `currency_symbol`, `name` (`packages/ecommerce/src/Http/Controllers/Api/StoreController.php`).
  - `GET /api/cms/settings/{group}` gives a custom settings group (CMS → Settings → Custom Settings) as `{key: value}`. It's public, so it must never hold a secret.
  - `GET /api/cms/menus/{slug}` gives a CMS menu's active items as a tree: `label`, `url`, `target`, `children`.
  - The catalogue, cart, orders and enquiries are `/api/products`, `/api/categories`, `/api/cart…`, `/api/orders…` and `/api/enquiries` (`packages/ecommerce/src/Http/Controllers/Api`, `tests/Feature/StorefrontApiTest.php`). The cart is identified by an `X-Cart-Token` header the browser makes up and keeps.
- **Forms:**
  - They post to `/api/cms/form-submissions` with fields named `<form>_<field>`. A form name has no underscore.
  - `StorefrontSeeder` creates each form and its field rules.
  - Keep the names the admin import gives the old messages: form `contact` (name, email, phone, role, location, message) and form `catalog` (name, contact_number).
- **The closest example:** a store already converted from the same old main branch. If `../vetheal-ecom` exists, read its `database/seeders/storefront`:
  - the `window.Store` client in its layout's `<head>`
  - components such as site-header, cart-drawer, search-modal, quick-view-modal, product-catalog, product-detail, cart-page, checkout-form, order-confirmation, order-tracker and enquiry-form

  Its old storefront started from the same Blade views, so reuse its components' behaviour (API calls, cart, checkout) and give them this store's markup and styles. **Don't copy three things from it:**
  - its `get(path, ttl)` copies of API answers in `sessionStorage`
  - its contact details and navigation written into the markup
  - any place where it shows content first and corrects it after the data loads

  Section 3 replaces all three.

## 3. Store data, loading and motion

This matters more than anything else in the conversion. Build it in the layout and the shared client first, and build every component on it.

- **One boot, before anything shows.**
  - The layout's `<head>` starts the requests every page needs at once: the feature flags (`/api/store`), the store's details (`/api/cms/settings/{group}`), the header and footer menus (`/api/cms/menus/{slug}`) and, when the cart is on, the cart.
  - All of them start in parallel; `window.Store.boot` resolves when they're all in.
  - Until then the page is laid out but not visible: the `<html>` element carries a booting class, and the content under it has `opacity: 0`, not `display: none`, so the markup is already in place.
  - The visitor sees the loading state described below.
  - Once boot resolves, the header, footer and everything else that depends on flags, settings or menus is filled in **before** the class comes off, and only then does the page appear.
  - So nothing a visitor sees ever changes because data arrived: no "Add to Cart" turning into "Enquire", no price that disappears, no default phone number that changes, no empty menu that fills.
- **The loading state.**
  - A calm, branded loader: the store's logo or mark with a subtle progress line or pulse, centred, in the store's colours. No generic spinner, and no loader for a fast load: show it only when boot takes longer than about 150 ms, so a quick load goes straight to the page.
  - If boot fails or takes longer than about 10 s, show a short message with a Retry button, never an endless loader.
  - Add a `<noscript>` rule that shows the content without JavaScript.
- **Data inside the page.**
  - Sections whose own data loads after boot (product grids, the product page, cart, checkout, order tracking) show skeletons of their final shape: the same grid, card sizes, image aspect ratios and line lengths, with a soft shimmer.
  - When the data arrives they crossfade into the real content with no layout shift: reserve image space with `aspect-ratio` or width/height.
  - A section never shows sample text, an empty list or a "0" while it loads. An empty result gets a designed empty state.
  - A failed request gets an inline message with Retry.
- **Feature flags.**
  - Every flag the store serves is followed everywhere: no prices, totals or offers when `pricing` is off; no stock badges when `stock` is off.
  - The cart icon, drawer and cart page only exist when `cart` is on; checkout when `orders` is on; the enquiry flow when `enquiry` is on, and both when `both_checkouts`.
  - Button and page labels come from `hero_label`, `add_label`, `checkout_label` and `cart_noun`, and money uses `currency_symbol`.
  - Elements that a flag turns off are never rendered and then removed.
  - When `/api/store` itself fails, the boot shows the failure state rather than guessing.
- **Common details come from settings.**
  - Create a custom settings group in `StorefrontSeeder` (`SettingGroup` and `SettingField` in `packages/cms/src/Models`; keys are snake_case) holding what the old pages show: phone, WhatsApp, email, address, opening hours, social links, the footer's short text.
  - Fill it from the old `settings` table or the rendered pages.
  - The header, footer, contact page and every other place read these values from `/api/cms/settings/{group}`. None of them is written into markup, so the store owner changes them in the panel.
- **Navigation comes from menus.**
  - Create the header and footer menus (and any other link lists, such as policies) in `StorefrontSeeder` with the old site's links, labels, order and nesting (`Menu`, `MenuItem`).
  - The header and footer render them from `/api/cms/menus/{slug}`, dropdowns included.
- **No caching.**
  - Every request asks the server again: `fetch(…, { cache: 'no-store' })`.
  - Nothing keeps copies of API answers: no `localStorage`/`sessionStorage`/IndexedDB copies, no TTLs, no service worker, nothing carried from one page to the next.
  - The only thing kept in the browser is the cart's `X-Cart-Token`, which is the cart's identity, not a cache.
  - Within one page, components share the boot's answers, and identical requests already in flight are shared.
- **Motion.**
  - The reveal after boot is one quick fade (around 250–350 ms, ease-out). Sections entering the viewport may rise a few pixels as they fade in.
  - Hovers and the cart drawer ease rather than jump. Nothing bounces, spins or loops while the visitor reads.
  - All motion uses opacity and transform only, and is switched off under `prefers-reduced-motion`.
- **The page editor.** None of this runs in the GrapesJS canvas: there, the content is visible at once, with no gate, loader or reveal. See how the current `layout.js` files detect the editor.

## 4. Convert

- **Layout.**
  - The old master layout's `<head>` (fonts, CSS, favicon, meta) and its page frame become `layout.html`, with the boot from section 3.
  - The header, the footer and whatever else is on every page (cart drawer, search, modals) become components placed with `<x-slug … />` tags.
  - Old JS that runs on every page goes into `layout.js`, guarded for the editor.
- **CSS.**
  - Serve the CSS the old site served. The compiled files from `public/` (`public/build` for Vite) go into `layout.css`, or into media and get linked from the layout, so the classes in the markup keep working.
  - Don't load Tailwind or any other framework from a CDN at runtime unless the old site did.
- **Static pages** (about, policies, …).
  - Each becomes `pages/{slug}.html`, made from the rendered page's main content, with the exact text, headings and images.
  - No Blade or PHP is left anywhere: no `@if`/`@foreach`/`@include`, and no `{{ }}` but the layout placeholders and route parameters.
- **Pages with data** (home sections, shop and category listings, product, cart, checkout, order confirmation, order tracking, enquiry, contact).
  - The markup goes into components whose `script.js` loads the data from the API and fills it in, following section 3.
  - What an editor may want to change (how many products, which category, a heading) becomes a prop in `component.json`.
- **URLs.**
  - Keep the old URLs, with a page slug or a `route_pattern` for each, so links, bookmarks and search results still work.
  - Where the API finds things differently (an id where the old URL had a slug), tell the user rather than changing the URL silently.
- **Media.**
  - The images, fonts and vendor files the pages use go into `media/<store-slug>/…` with their old relative paths, and are referenced as `/media/<store-slug>/…`.
  - Leave product and category pictures out: the admin import brings those.
- **SEO.**
  - Each page's `<title>` and meta description from `rendered/` go into `pages.json` (`title`, `seo_head`).
  - The home page gets `is_home`.
- **Keep pages editable.** Pages are plain markup; behaviour lives in component scripts or `layout.js`.

## 5. Where to write

- Work on a new branch.
- Replace `database/seeders/storefront` with the converted site.
- Update `StorefrontSeeder`: site name, support email, forms, the custom settings group and the menus.
- Change nothing under `packages/`. If the storefront needs something the API doesn't give, stop and tell the user.

## 6. Check it

- **Before running any test or seeder:** if `bootstrap/cache/config.php` exists, the cached config wins over phpunit.xml and `RefreshDatabase` wipes the real database. Either run `composer test`, which clears the config first, or put cache paths that don't exist in front of the command: `APP_CONFIG_CACHE=… APP_ROUTES_CACHE=… APP_EVENTS_CACHE=…`.
- **Tests:**
  - Write a feature test, following the store's existing storefront tests, that seeds `StorefrontSeeder` and requests every page in the inventory.
  - Each page answers 200 with its title and its components, and no Blade is left in it.
  - The settings group and menus exist with the old values.
  - Run it and the whole suite.
- **Look at it in a browser.**
  - Point `DB_CONNECTION=sqlite DB_DATABASE=<scratch file>` at a scratch database and fill it with `migrate --seed`. If you can, also run `ecommerce:import-legacy` from the same zip so real products show.
  - Never use the user's own database without asking.
  - Serve the app and compare every page with its `rendered/` file at desktop and phone widths. Then check the behaviour in section 3:
    - **Throttle the network** (slow 3G, or delayed API routes in the browser tool) and watch every page load: the loader, then the finished page, with nothing visible changing afterwards and no layout shift.
    - **Fail `/api/store`** and check that you get the Retry state, not an endless loader or guessed content.
    - **Flip the flags** in the panel (orders off and enquiry on, pricing off, stock off, cart off) and reload: every page follows, with nothing flashing.
    - **Change a phone number and a menu item** in the panel, reload, and see them change without a rebuild.
    - **Look for caches:** `grep -rn "localStorage\|sessionStorage\|indexedDB\|serviceWorker\|caches\." database/seeders/storefront` finds only the cart token.
    - **Check the editor and reduced motion:** with `prefers-reduced-motion` the page appears without motion, and in the page editor the content shows at once.
- **Zip for CMS → Import / Export Site:** `(cd database/seeders/storefront && zip -r ../../../storage/app/storefront.zip .)`. The zip stays out of git, since it's rebuilt from the folder.

## 7. Report

- Give the inventory with what each old URL became.
- List the settings group's keys and the menus you created.
- Say what was dropped or approximated and why, and which decisions are waiting for the user.
- Don't commit or push unless the user asks.
