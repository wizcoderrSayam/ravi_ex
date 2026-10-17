<?php

namespace Tests\Feature;

use Carepeerinfotech\Ecommerce\Support\PublicStorageLink;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Runs against a throwaway public/ and storage/, never the project's real ones.
 */
class PublicStorageLinkTest extends TestCase
{
    private string $sandbox;

    private string $publicPath;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'storage-link-'.str_replace('.', '', uniqid('', true));
        $this->publicPath = $this->sandbox.DIRECTORY_SEPARATOR.'public';
        $this->diskRoot = $this->path($this->sandbox, 'storage', 'app', 'public');

        File::ensureDirectoryExists($this->publicPath);
        File::ensureDirectoryExists($this->diskRoot);

        $this->app->usePublicPath($this->publicPath);
        $this->app->useStoragePath($this->sandbox.DIRECTORY_SEPARATOR.'storage');
        config(['filesystems.disks.public.root' => $this->diskRoot]);
    }

    protected function tearDown(): void
    {
        $link = $this->path($this->publicPath, 'storage');

        // Remove the link itself first so deleting the sandbox never follows it.
        if (is_link($link) || (@readlink($link) ?: $link) !== $link) {
            @unlink($link) || @rmdir($link);
        }

        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    public function test_it_links_public_storage_to_the_disk_root(): void
    {
        $this->assertFalse(PublicStorageLink::isReady());

        $result = PublicStorageLink::ensure();

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('symlink', $result['method']);
        $this->assertTrue(PublicStorageLink::isReady());

        // A file uploaded afterwards is served through the link straight away.
        File::put($this->path($this->diskRoot, 'new-upload.jpg'), 'image');
        $this->assertFileExists($this->path($this->publicPath, 'storage', 'new-upload.jpg'));
    }

    public function test_the_link_is_relative_on_posix_hosts(): void
    {
        if (windows_os()) {
            $this->markTestSkipped('Windows uses an absolute junction.');
        }

        PublicStorageLink::ensure();

        $this->assertSame('../storage/app/public', readlink($this->path($this->publicPath, 'storage')));
    }

    public function test_an_existing_link_is_kept(): void
    {
        PublicStorageLink::ensure();

        $result = PublicStorageLink::ensure();

        $this->assertTrue($result['ok']);
        $this->assertSame('Public storage is already linked.', $result['message']);
        $this->assertTrue(PublicStorageLink::isReady());
    }

    public function test_a_leftover_real_folder_is_copied_in_and_kept_as_a_backup(): void
    {
        $oldFolder = $this->path($this->publicPath, 'storage');
        File::ensureDirectoryExists($this->path($oldFolder, 'categories'));
        File::put($this->path($oldFolder, '.gitignore'), "*\n!.gitignore\n");
        File::put($this->path($oldFolder, 'categories', 'only-here.jpg'), 'uploaded in the old mode');
        File::put($this->path($oldFolder, 'categories', '.hidden.jpg'), 'dotfile upload');
        File::put($this->path($oldFolder, 'categories', 'both.jpg'), 'folder copy, longer');
        File::ensureDirectoryExists($this->path($this->diskRoot, 'categories'));
        File::put($this->path($this->diskRoot, 'categories', 'both.jpg'), 'disk copy');

        // Production's folder also held a stray link back to the disk root.
        if (! windows_os()) {
            symlink($this->diskRoot, $this->path($oldFolder, 'public'));
        }

        $result = PublicStorageLink::ensure();

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertTrue(PublicStorageLink::isReady());
        $this->assertSame('uploaded in the old mode', File::get($this->path($this->diskRoot, 'categories', 'only-here.jpg')));
        $this->assertSame('dotfile upload', File::get($this->path($this->diskRoot, 'categories', '.hidden.jpg')));
        $this->assertSame('disk copy', File::get($this->path($this->diskRoot, 'categories', 'both.jpg')));
        $this->assertStringContainsString('1 file(s) there differed', $result['message']);

        // The old folder is kept outside the web root, so its differing copy survives.
        $backups = glob($this->path($this->sandbox, 'storage', 'app', 'public-storage-backup-*'));
        $this->assertCount(1, $backups);
        $this->assertSame('folder copy, longer', File::get($this->path($backups[0], 'categories', 'both.jpg')));
        $this->assertSame([], glob($this->path($this->publicPath, 'storage-*')));
    }

    public function test_a_real_folder_is_used_when_the_disk_root_is_public_storage(): void
    {
        config(['filesystems.disks.public.root' => $this->path($this->publicPath, 'storage')]);

        $result = PublicStorageLink::ensure();

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('directory', $result['method']);
        $this->assertTrue(PublicStorageLink::isReady());
        $this->assertDirectoryExists($this->path($this->publicPath, 'storage'));
        $this->assertFalse(is_link($this->path($this->publicPath, 'storage')));
    }

    private function path(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
