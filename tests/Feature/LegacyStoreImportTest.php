<?php

namespace Tests\Feature;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Peer\CMS\Facades\CMS;
use Peer\CMS\Models\Category;
use Peer\CMS\Models\Form;
use Peer\Ecommerce\Models\CategoryMedia;
use Peer\Ecommerce\Models\Product;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;
use ZipArchive;

class LegacyStoreImportTest extends TestCase
{
    use RefreshDatabase;

    private string $oldApp;

    private string $zip;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));

        $this->oldApp = sys_get_temp_dir().'/old-store-'.Str::random(8);
        $this->zip = $this->oldApp.'.zip';
        $this->makeOldStore();
    }

    protected function tearDown(): void
    {
        DB::purge('old');
        File::deleteDirectory($this->oldApp);
        File::delete($this->zip);

        parent::tearDown();
    }

    public function test_the_export_packs_the_old_database_env_and_files(): void
    {
        $this->export();

        $zip = new ZipArchive;
        $zip->open($this->zip);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        $this->assertSame(1, $manifest['format']);
        $this->assertSame('Old Store', $manifest['app']['name']);
        $this->assertSame(1, $manifest['tables']['orders']);
        $this->assertArrayNotHasKey('sessions', $manifest['tables']);
        $this->assertStringContainsString('APP_NAME="Old Store"', (string) $zip->getFromName('.env'));
        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $this->assertNotFalse($zip->locateName('storage/app/public/products/pill.png'));
        $this->assertNotFalse($zip->locateName('public/images/catalogue.pdf'));
        $this->assertNotFalse($zip->locateName('public/build/app.css'));
        $this->assertFalse($zip->locateName('public/storage/products/pill.png'), 'The public/storage symlink is followed.');
        $this->assertNotFalse($zip->locateName('resources/views/home.blade.php'));
        $this->assertNotFalse($zip->locateName('routes/web.php'));
        $this->assertNotFalse($zip->locateName('package.json'));
        $this->assertSame([], $manifest['pages'], 'The .env has no APP_URL, so no live pages are read.');
        $zip->close();
    }

    public function test_the_export_saves_the_live_pages_the_home_page_links_to(): void
    {
        $result = $this->exportFromSite(<<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($path === '/') {
                $links = ['/about', '/admin/orders', '/login', '/images/logo.png', 'mailto:hi@old.test', 'https://elsewhere.test/x', '/moved', 'contact'];
                foreach (range(1, 6) as $number) {
                    $links[] = "/product/item-{$number}";
                }
                echo '<html><body>'.implode('', array_map(fn ($href) => "<a href=\"{$href}\">link</a>", $links)).'</body></html>';
            } elseif ($path === '/moved') {
                header('Location: /login', true, 302);
            } elseif (in_array($path, ['/about', '/contact'], true) || str_starts_with($path, '/product/')) {
                echo "<html><body><h1>{$path}</h1></body></html>";
            } else {
                http_response_code(404);
            }
            PHP, '');

        $this->assertTrue($result->successful(), $result->errorOutput());

        $zip = new ZipArchive;
        $zip->open($this->zip);
        $pages = array_column(json_decode((string) $zip->getFromName('manifest.json'), true)['pages'], 'url');

        $this->assertSame(['/', '/about', '/contact', '/product/item-1', '/product/item-2', '/product/item-3', '/product/item-4'], $pages);
        $this->assertSame('<html><body><h1>/product/item-1</h1></body></html>', $zip->getFromName('rendered/product/item-1.html'));
        $this->assertNotFalse($zip->locateName('rendered/index.html'));
        $zip->close();
    }

    public function test_a_store_in_a_folder_of_its_site_keeps_to_that_folder(): void
    {
        $result = $this->exportFromSite(<<<'PHP'
            <?php
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if ($path === '/shop/') {
                echo '<a href="/shop/about">About</a><a href="/blog/post">Blog</a><a href="/shop">Home</a><a href="contact">Contact</a><a href="/shop/admin">Admin</a>';
            } elseif (in_array($path, ['/shop/about', '/shop/contact', '/blog/post'], true)) {
                echo "<h1>{$path}</h1>";
            } else {
                http_response_code(404);
            }
            PHP, '/shop');

        $this->assertTrue($result->successful(), $result->errorOutput());

        $zip = new ZipArchive;
        $zip->open($this->zip);

        $this->assertSame(['/shop/', '/shop/about', '/shop/contact'], array_column(json_decode((string) $zip->getFromName('manifest.json'), true)['pages'], 'url'));
        $this->assertSame('<h1>/shop/about</h1>', $zip->getFromName('rendered/about.html'));
        $this->assertNotFalse($zip->locateName('rendered/index.html'));
        $zip->close();
    }

    public function test_the_export_refuses_to_write_the_zip_into_the_old_apps_folder(): void
    {
        $result = Process::run([PHP_BINARY, $this->script(), $this->oldApp, $this->oldApp.'/store.zip']);

        $this->assertFalse($result->successful());
        $this->assertStringContainsString('outside the old app\'s folder', $result->errorOutput());
        $this->assertFileDoesNotExist($this->oldApp.'/store.zip');
    }

    public function test_the_import_brings_the_old_admin_panel_over(): void
    {
        Role::query()->create(['name' => 'Admin', 'slug' => 'admin']);
        $this->export();

        $this->artisan('ecommerce:import-legacy', ['zip' => $this->zip, '--no-backup' => true])->assertSuccessful();

        $user = DB::table('users')->where('email', 'asha@example.com')->first();
        $this->assertTrue(Hash::check('old-password', $user->password));
        $this->assertTrue(DB::table('role_user')->where('user_id', $user->id)->exists());

        $product = Product::query()->where('sku', 'PILL-1')->firstOrFail();
        $this->assertCount(1, $product->images);
        Storage::disk(config('cms.media.disk'))->assertExists($product->images[0]);
        $this->assertSame(['Tablets'], $product->categories->pluck('name')->all());

        $category = Category::query()->where('name', 'Tablets')->firstOrFail();
        $categoryMedia = CategoryMedia::query()->where('category_id', $category->id)->sole();
        $this->assertSame('tablets.png', basename($categoryMedia->path));
        $this->assertSame('image/png', $categoryMedia->mime_type);
        $this->assertSame($category->id, Category::query()->where('name', 'Chewable')->value('parent_id'), 'A child listed before its parent still lands under it.');

        $order = DB::table('orders')->where('order_number', 'ORD-1001')->first();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(DB::table('coupons')->where('code', 'SAVE10')->value('id'), $order->coupon_id);
        $this->assertSame($product->id, DB::table('order_items')->where('order_id', $order->id)->value('product_id'));
        $this->assertDatabaseHas('enquiries', ['enquiry_number' => 'ENQ-1', 'user_id' => $user->id]);

        $this->assertSame(['name' => 'Ravi', 'email' => 'ravi@example.com', 'message' => 'Hello'], json_decode(DB::table('form_submissions')->where('form', 'contact')->value('fields'), true));
        $this->assertDatabaseHas('form_submissions', ['form' => 'catalog']);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'news@example.com']);

        $settings = CMS::settings();
        $this->assertSame('Old Store', $settings->get('general.app_name'));
        $this->assertSame('secret-pass', $settings->get('smtp.password'), 'Decrypted with the APP_KEY of the zip\'s .env.');
        $this->assertSame('smtp.old.test', $settings->get('smtp.host'), 'Taken from the .env, as the old setting is empty.');
        $this->assertSame('admin@old.test', $settings->get('notifications.to'));
        $this->assertSame('shiprocket', $settings->get('shipping_provider.driver'));
        $this->assertTrue((bool) $settings->get('features.orders_enabled'), 'The old store had no feature switches and took orders.');
        $this->assertTrue((bool) $settings->get('features.enquiry_enabled'), 'The old store had no feature switches and had enquiries.');

        $this->assertSame('Welcome from the old store', DB::table('email_templates')->where('slug', 'account_created')->value('subject'));
    }

    public function test_a_store_from_before_enquiries_and_catalog_leads_imports_the_rest(): void
    {
        $old = Schema::connection('old');
        $old->drop('enquiries');
        $old->drop('enquiry_items');
        $old->drop('catalog_download_leads');
        $old->table('users', fn (Blueprint $table) => $table->dropColumn('phone_verified_at'));
        $old->table('images', fn (Blueprint $table) => $table->dropColumn('sort_order'));
        $this->export();

        $this->artisan('ecommerce:import-legacy', ['zip' => $this->zip, '--no-backup' => true])
            ->expectsOutputToContain('Not in the old store, so nothing came from them: enquiries, catalog_download_leads.')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('orders')->count());
        $this->assertCount(1, Product::query()->where('sku', 'PILL-1')->sole()->images);
        $this->assertSame(0, DB::table('enquiries')->count());
        $this->assertFalse((bool) CMS::settings()->get('features.enquiry_enabled'), 'A store without enquiries keeps them off.');
        $this->assertTrue(Form::query()->where('name', 'contact')->exists());
        $this->assertFalse(Form::query()->where('name', 'catalog')->exists(), 'No catalog form for a store that had no catalog downloads.');
    }

    public function test_importing_a_newer_zip_adds_new_orders_without_duplicating_the_rest(): void
    {
        $this->export();
        $this->artisan('ecommerce:import-legacy', ['zip' => $this->zip, '--no-backup' => true])->assertSuccessful();

        DB::connection('old')->table('orders')->insert($this->oldOrder(2, 'ORD-1002'));
        $this->export();
        $this->artisan('ecommerce:import-legacy', ['zip' => $this->zip, '--no-backup' => true])->assertSuccessful();

        $this->assertSame(['ORD-1001', 'ORD-1002'], DB::table('orders')->orderBy('order_number')->pluck('order_number')->all());
        $this->assertSame(1, DB::table('order_items')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'asha@example.com')->count());
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, CategoryMedia::query()->count());
        $this->assertSame(2, DB::table('form_submissions')->count());
    }

    public function test_a_zip_the_export_did_not_make_is_refused(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->zip, ZipArchive::CREATE);
        $zip->addFromString('dump.sql', '');
        $zip->close();

        $this->artisan('ecommerce:import-legacy', ['zip' => $this->zip, '--no-backup' => true])
            ->expectsOutputToContain('no manifest.json')
            ->assertFailed();
    }

    private function script(): string
    {
        return base_path('packages/ecommerce/bin/export-legacy.php');
    }

    /**
     * Exports the old store with its live pages read from a local site, which $router serves,
     * at $folder of it ('' for its root).
     */
    private function exportFromSite(string $router, string $folder): ProcessResult
    {
        file_put_contents($this->oldApp.'/site.php', $router);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $site = Process::start([PHP_BINARY, '-S', "127.0.0.1:{$port}", $this->oldApp.'/site.php']);
        try {
            for ($attempt = 0; $attempt < 50 && ! @fsockopen('127.0.0.1', $port, $errorCode, $error, 0.1); $attempt++) {
                usleep(100_000);
            }

            return Process::run([PHP_BINARY, $this->script(), $this->oldApp, $this->zip, "--url=http://127.0.0.1:{$port}{$folder}"]);
        } finally {
            $site->stop();
        }
    }

    private function export(): ProcessResult
    {
        DB::purge('old');
        $result = Process::run([PHP_BINARY, $this->script(), $this->oldApp, $this->zip]);
        $this->assertTrue($result->successful(), $result->errorOutput());

        return $result;
    }

    /**
     * An old store on SQLite, with the tables the importer reads, one row of each kind, pictures on
     * its public disk and public/storage linked to it.
     */
    private function makeOldStore(): void
    {
        foreach (['database', 'storage/app/public/products', 'storage/app/public/categories', 'public/images', 'public/build', 'resources/views', 'routes'] as $folder) {
            File::ensureDirectoryExists($this->oldApp.'/'.$folder);
        }

        file_put_contents($this->oldApp.'/resources/views/home.blade.php', '@extends(\'layouts.app\')');
        file_put_contents($this->oldApp.'/routes/web.php', '<?php');
        file_put_contents($this->oldApp.'/package.json', '{}');

        $png = UploadedFile::fake()->image('pill.png', 8, 8)->getContent();
        file_put_contents($this->oldApp.'/storage/app/public/products/pill.png', $png);
        file_put_contents($this->oldApp.'/storage/app/public/categories/tablets.png', $png);
        file_put_contents($this->oldApp.'/public/images/catalogue.pdf', '%PDF-1.4');
        file_put_contents($this->oldApp.'/public/build/app.css', '');
        symlink($this->oldApp.'/storage/app/public', $this->oldApp.'/public/storage');

        $key = Encrypter::generateKey('AES-256-CBC');
        file_put_contents($this->oldApp.'/.env', implode("\n", [
            'APP_NAME="Old Store"',
            'APP_KEY=base64:'.base64_encode($key),
            'DB_CONNECTION=sqlite',
            'DB_DATABASE=database/database.sqlite',
            'MAIL_MAILER=smtp',
            'MAIL_HOST=smtp.old.test # the old SMTP server',
        ]));

        touch($this->oldApp.'/database/database.sqlite');
        config(['database.connections.old' => ['driver' => 'sqlite', 'database' => $this->oldApp.'/database/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => false]]);

        $this->createOldTables();

        $old = DB::connection('old');
        $now = '2026-01-15 10:00:00';
        $old->table('users')->insert(['id' => 1, 'name' => 'Asha', 'email' => 'asha@example.com', 'password' => Hash::make('old-password'), 'is_active' => 1, 'is_customer' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $old->table('roles')->insert(['id' => 1, 'name' => 'Admin', 'slug' => 'admin']);
        $old->table('role_user')->insert(['user_id' => 1, 'role_id' => 1]);
        $old->table('categories')->insert([
            ['id' => 1, 'parent_id' => null, 'name' => 'Tablets', 'is_active' => 1, 'sort_order' => 1],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Chewable', 'is_active' => 1, 'sort_order' => 0],
        ]);
        $old->table('coupons')->insert(['id' => 7, 'code' => 'SAVE10', 'discount_type' => 'percentage', 'value' => '10.00', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $old->table('products')->insert(['id' => 1, 'category_id' => 1, 'name' => 'Pill', 'slug' => 'pill', 'sku' => 'PILL-1', 'price' => '120.50', 'stock' => 5, 'is_featured' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $old->table('product_variants')->insert(['id' => 1, 'product_id' => 1, 'sku' => 'PILL-1', 'price' => '120.50', 'stock' => 5, 'is_default' => 1, 'is_active' => 1]);
        $old->table('images')->insert([
            ['imageable_type' => 'product', 'imageable_id' => 1, 'path' => 'products/pill.png', 'is_primary' => 1, 'sort_order' => 0],
            ['imageable_type' => 'category', 'imageable_id' => 1, 'path' => 'categories/tablets.png', 'is_primary' => 1, 'sort_order' => 0],
        ]);
        $old->table('orders')->insert($this->oldOrder(1, 'ORD-1001'));
        $old->table('order_items')->insert(['id' => 1, 'order_id' => 1, 'product_id' => 1, 'product_variant_id' => 1, 'product_name' => 'Pill', 'product_sku' => 'PILL-1', 'quantity' => 2, 'price' => '120.50', 'total' => '241.00', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('enquiries')->insert(['id' => 1, 'enquiry_number' => 'ENQ-1', 'user_id' => 1, 'customer_name' => 'Asha', 'customer_email' => 'asha@example.com', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('enquiry_items')->insert(['id' => 1, 'enquiry_id' => 1, 'product_id' => 1, 'product_variant_id' => 1, 'product_name' => 'Pill', 'product_sku' => 'PILL-1', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('contact_messages')->insert(['name' => 'Ravi', 'email' => 'ravi@example.com', 'phone' => '', 'message' => 'Hello', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('catalog_download_leads')->insert(['name' => 'Meena', 'contact_number' => '9876543210', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('newsletter_subscribers')->insert(['email' => 'news@example.com', 'created_at' => $now, 'updated_at' => $now]);
        $old->table('settings')->insert([
            ['group' => 'commerce', 'key' => 'store_name', 'value' => 'Old Store', 'is_encrypted' => 0],
            ['group' => 'email', 'key' => 'host', 'value' => '', 'is_encrypted' => 0],
            ['group' => 'email', 'key' => 'password', 'value' => (new Encrypter($key, 'AES-256-CBC'))->encryptString('secret-pass'), 'is_encrypted' => 1],
            ['group' => 'email', 'key' => 'admin_email', 'value' => 'admin@old.test', 'is_encrypted' => 0],
            ['group' => 'shiprocket', 'key' => 'driver', 'value' => 'live', 'is_encrypted' => 0],
        ]);
        $old->table('email_templates')->insert(['slug' => 'account_created', 'name' => 'Account created', 'subject' => 'Welcome from the old store', 'body' => '<p>Hi</p>', 'placeholders' => 'not json', 'is_active' => 1]);
        $old->table('sessions')->insert(['id' => 'abc', 'payload' => 'x']);
    }

    /**
     * @return array<string, mixed>
     */
    private function oldOrder(int $id, string $number): array
    {
        return [
            'id' => $id, 'order_number' => $number, 'user_id' => 1, 'customer_name' => 'Asha', 'customer_email' => 'asha@example.com',
            'shipping_address' => '1 Main Road', 'shipping_city' => 'Pune', 'subtotal' => '241.00', 'total' => '216.90', 'status' => 'pending',
            'coupon_id' => 7, 'coupon_code' => 'SAVE10', 'coupon_discount' => '24.10',
            'created_at' => '2026-01-15 10:00:00', 'updated_at' => '2026-01-15 10:00:00',
        ];
    }

    private function createOldTables(): void
    {
        $schema = Schema::connection('old');
        $timestamps = fn (Blueprint $table) => $table->timestamps();

        $schema->create('users', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('is_active');
            $table->boolean('is_customer');
            $timestamps($table);
        });
        $schema->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
        });
        $schema->create('role_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
        });
        $schema->create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('address_line1');
            $table->string('postal_code');
        });
        $schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->boolean('is_active');
            $table->integer('sort_order');
        });
        $schema->create('products', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            foreach (['brand', 'hsn_code', 'short_description', 'description', 'meta_title', 'meta_description'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('price', 10, 2);
            foreach (['compare_price', 'weight', 'length', 'breadth', 'height'] as $column) {
                $table->decimal($column, 10, 2)->nullable();
            }
            $table->integer('stock');
            $table->boolean('is_featured');
            $table->boolean('is_active');
            $timestamps($table);
        });
        $schema->create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('sku');
            $table->string('name')->nullable();
            $table->string('option_label')->nullable();
            $table->text('option_values')->nullable();
            $table->decimal('price', 10, 2);
            foreach (['compare_price', 'weight', 'length', 'breadth', 'height'] as $column) {
                $table->decimal($column, 10, 2)->nullable();
            }
            $table->integer('stock');
            $table->boolean('is_default');
            $table->boolean('is_active');
        });
        $schema->create('product_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->integer('sort_order');
        });
        $schema->create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_option_id');
            $table->string('value');
            $table->integer('sort_order');
        });
        $schema->create('images', function (Blueprint $table) {
            $table->id();
            $table->string('imageable_type');
            $table->unsignedBigInteger('imageable_id');
            $table->string('path');
            $table->boolean('is_primary');
            $table->integer('sort_order');
        });
        $schema->create('product_offers', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_offer_id')->nullable();
            $table->string('name');
            $table->string('discount_type');
            $table->decimal('value', 10, 2);
            $table->boolean('apply_to_category');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $timestamps($table);
        });
        $schema->create('orders', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('order_number');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->text('shipping_address');
            $table->string('shipping_city');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('coupon_discount', 10, 2)->default(0);
            $table->string('status');
            $timestamps($table);
        });
        $schema->create('coupons', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('code');
            $table->string('discount_type');
            $table->decimal('value', 10, 2);
            $table->boolean('is_active');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $timestamps($table);
        });
        $schema->create('order_items', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name');
            $table->string('product_sku');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);
            $timestamps($table);
        });
        $schema->create('order_offers', function (Blueprint $table) {
            $table->id();
            foreach (['order_id', 'order_item_id', 'product_id', 'product_offer_id'] as $column) {
                $table->unsignedBigInteger($column)->nullable();
            }
        });
        $schema->create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
        });
        $schema->create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
        });
        $schema->create('shipment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipment_id')->nullable();
        });
        $schema->create('enquiries', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('enquiry_number');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $timestamps($table);
        });
        $schema->create('enquiry_items', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->unsignedBigInteger('enquiry_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $timestamps($table);
        });
        $schema->create('contact_messages', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');
            $timestamps($table);
        });
        $schema->create('catalog_download_leads', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('name');
            $table->string('contact_number');
            $timestamps($table);
        });
        $schema->create('newsletter_subscribers', function (Blueprint $table) use ($timestamps) {
            $table->id();
            $table->string('email');
            $timestamps($table);
        });
        $schema->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group');
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted');
        });
        $schema->create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->text('placeholders')->nullable();
            $table->boolean('is_active');
        });
        $schema->create('sessions', function (Blueprint $table) {
            $table->string('id');
            $table->text('payload');
        });
    }
}
