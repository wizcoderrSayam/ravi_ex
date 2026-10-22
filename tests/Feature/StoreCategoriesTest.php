<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Peer\Ecommerce\Contracts\MediaLibrary;
use Peer\Ecommerce\Models\CategoryMedia;
use Peer\Ecommerce\Models\Role;
use Tests\TestCase;

class StoreCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('cms.media.disk'));
        $this->actingAs($this->admin());
    }

    public function test_store_categories_takes_the_place_of_cms_categories_in_the_sidebar(): void
    {
        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.categories.index').'"', false)
            ->assertDontSee('href="'.route('cms.panel.categories.index').'"', false);

        $this->get(route('cms.panel.categories.index'))->assertOk();
    }

    public function test_the_tree_starts_with_subcategories_collapsed_under_toggles_and_shows_media_thumbnails(): void
    {
        $livestock = $this->category('Livestock');
        $cattle = $this->category('Cattle', $livestock);
        $this->putFile('categories/cattle.png', base64_decode(self::PNG));
        $this->putFile('categories/cattle.mp4', 'not really a video');
        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.png', 'categories/cattle.mp4']])->assertOk();
        $library = app(MediaLibrary::class);

        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-depth="0" data-name="Livestock" >',
                'aria-label="Show the subcategories of Livestock"',
                '1 subcategory',
                'data-depth="1" data-name="Cattle" hidden>',
                '<img src="'.$library->url('categories/cattle.png').'"',
                '<video src="'.$library->url('categories/cattle.mp4').'#t=0.1"',
            ], false)
            ->assertDontSee('subcategories of Cattle', false);
    }

    public function test_a_category_is_never_offered_as_a_parent_of_itself_or_of_its_subcategories(): void
    {
        $livestock = $this->category('Livestock');
        $this->category('Cattle', $livestock);
        $pets = $this->category('Pets');

        $parents = $this->get(route('admin.categories.edit', $livestock))
            ->assertOk()
            ->viewData('parents');

        $this->assertSame([$pets], array_map(fn (array $entry) => $entry['category']->id, $parents));
    }

    public function test_a_categorys_media_keep_each_files_path_mime_type_and_size_in_order(): void
    {
        $cattle = $this->category('Cattle');
        $this->putFile('categories/cattle.png', base64_decode(self::PNG));
        $this->putFile('categories/cattle.mp4', 'not really a video');

        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.mp4', 'categories/cattle.png']])
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => route('admin.categories.index')]);

        $this->assertSame([
            ['path' => 'categories/cattle.mp4', 'mime_type' => 'video/mp4', 'size' => 18, 'sort_order' => 0],
            ['path' => 'categories/cattle.png', 'mime_type' => 'image/png', 'size' => strlen(base64_decode(self::PNG)), 'sort_order' => 1],
        ], $this->mediaOf($cattle));

        $png = CategoryMedia::firstWhere('path', 'categories/cattle.png');
        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.png']])->assertOk();

        $this->assertSame([$png->id], CategoryMedia::pluck('id')->all());
        $this->assertSame(0, $png->fresh()->sort_order);
    }

    public function test_only_images_and_videos_still_in_the_media_library_can_be_added(): void
    {
        $cattle = $this->category('Cattle');
        $this->putFile('categories/price-list.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n");

        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/price-list.pdf', 'categories/gone.png', '../.env']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'media.0' => 'Only images and videos can be added to a category.',
                'media.1' => 'One of the chosen files is no longer in the media library.',
                'media.2' => 'One of the chosen files is no longer in the media library.',
            ]);

        $this->assertSame(0, CategoryMedia::count());
    }

    public function test_a_moved_file_stays_with_its_category(): void
    {
        $cattle = $this->category('Cattle');
        $this->putFile('categories/cattle.png', base64_decode(self::PNG));
        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.png']])->assertOk();

        event('cms.media.relocated', [[
            'id' => 1,
            'disk' => config('cms.media.disk'),
            'from' => ['path' => 'categories/cattle.png'],
            'to' => ['path' => 'livestock/cattle.png'],
        ]]);

        $this->assertSame('livestock/cattle.png', CategoryMedia::sole()->path);
    }

    public function test_a_categorys_media_go_only_once_the_tree_has_deleted_the_category(): void
    {
        $cattle = $this->category('Cattle');
        $this->putFile('categories/cattle.png', base64_decode(self::PNG));
        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.png']])->assertOk();

        $this->deleteJson(route('admin.categories.media.destroy', $cattle))->assertUnprocessable();
        $this->assertSame(1, CategoryMedia::count());

        $this->deleteJson(route('cms.panel.categories.destroy', $cattle))->assertOk();
        $this->deleteJson(route('admin.categories.media.destroy', $cattle))->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, CategoryMedia::count());
    }

    public function test_the_storefront_lists_a_categorys_media_with_its_first_image_as_its_picture(): void
    {
        $cattle = $this->category('Cattle');
        $this->category('Pets');
        $this->putFile('categories/cattle.mp4', 'not really a video');
        $this->putFile('categories/cattle.png', base64_decode(self::PNG));
        $this->putJson(route('admin.categories.media.update', $cattle), ['media' => ['categories/cattle.mp4', 'categories/cattle.png']])->assertOk();
        $library = app(MediaLibrary::class);

        $this->getJson(route('api.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.0.image', $library->url('categories/cattle.png'))
            ->assertJsonPath('data.0.media', [
                ['type' => 'video', 'url' => $library->url('categories/cattle.mp4'), 'mime_type' => 'video/mp4', 'size' => 18],
                ['type' => 'image', 'url' => $library->url('categories/cattle.png'), 'mime_type' => 'image/png', 'size' => strlen(base64_decode(self::PNG))],
            ])
            ->assertJsonPath('data.1.image', null)
            ->assertJsonPath('data.1.media', []);
    }

    private function category(string $name, ?int $parentId = null): int
    {
        return $this->postJson(route('cms.panel.categories.store'), ['name' => $name, 'parent_id' => $parentId])->json('data.id');
    }

    private function putFile(string $path, string $contents): void
    {
        Storage::disk(config('cms.media.disk'))->put($path, $contents);
    }

    /**
     * @return list<array{path: string, mime_type: string, size: int, sort_order: int}>
     */
    private function mediaOf(int $categoryId): array
    {
        return CategoryMedia::where('category_id', $categoryId)->ordered()->get()
            ->map(fn (CategoryMedia $file) => $file->only(['path', 'mime_type', 'size', 'sort_order']))
            ->all();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->roles()->attach(Role::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_active' => true]));

        return $admin;
    }
}
