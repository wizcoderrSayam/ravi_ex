<?php

namespace Tests\Feature;

use App\Models\User;
use Carepeerinfotech\Ecommerce\Database\Seeders\EcommerceSeeder;
use Carepeerinfotech\Ecommerce\Models\Role;
use Carepeerinfotech\Ecommerce\Services\Backup\BackupRestorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class AdminDevToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // Tests run on sqlite; point the backups at the mysql connection and fake the binaries.
        config(['dev-tools.connection' => 'mysql', 'dev-tools.mysql_bin_path' => null]);

        $this->seed(EcommerceSeeder::class);
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        parent::tearDown();
    }

    public function test_only_super_admins_can_open_dev_tools(): void
    {
        $this->get(route('admin.dev-tools.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->adminWithRole('admin'))
            ->get(route('admin.dev-tools.index'))
            ->assertForbidden();

        $this->fakeMysql();

        $this->actingAs($this->adminWithRole('super-admin'))
            ->get(route('admin.dev-tools.index'))
            ->assertOk()
            ->assertSee('Your applicaton will be destroyed but chill it will restored properly')
            ->assertSee('Exclude runtime tables');
    }

    public function test_dev_tools_link_is_hidden_from_other_admins(): void
    {
        $this->actingAs($this->adminWithRole('admin'))
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.dev-tools.index'));
    }

    public function test_export_skips_runtime_tables_by_default(): void
    {
        $this->fakeMysql();
        Storage::disk('public')->put('products/1/shirt.jpg', 'image');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.export'), [
                'include_media' => '0',
                'exclude_runtime_tables' => '1',
            ])
            ->assertRedirect(route('admin.dev-tools.index'))
            ->assertSessionHas('success');

        $zip = $this->openOnlyBackup();
        $manifest = json_decode($zip->getFromName('manifest.json'), true);

        $this->assertNotFalse($zip->getFromName('database.sql'));
        $this->assertFalse($zip->getFromName('media/products/1/shirt.jpg'));
        $this->assertTrue($manifest['excludes_runtime_tables']);
        $this->assertFalse($manifest['includes_media']);
        $zip->close();

        Process::assertRan(fn (PendingProcess $process) => $this->isBinary($process, 'mysqldump')
            && in_array('--ignore-table='.config('database.connections.mysql.database').'.sessions', $process->command, true)
            && in_array('--ignore-table='.config('database.connections.mysql.database').'.failed_jobs', $process->command, true));
    }

    public function test_export_can_include_media_and_runtime_tables(): void
    {
        $this->fakeMysql();
        Storage::disk('public')->put('products/1/shirt.jpg', 'image');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.export'), [
                'include_media' => '1',
                'exclude_runtime_tables' => '0',
            ])
            ->assertSessionHas('success');

        $zip = $this->openOnlyBackup();
        $manifest = json_decode($zip->getFromName('manifest.json'), true);

        $this->assertSame('image', $zip->getFromName('media/products/1/shirt.jpg'));
        $this->assertFalse($manifest['excludes_runtime_tables']);
        $this->assertTrue($manifest['includes_media']);
        $zip->close();

        Process::assertRan(fn (PendingProcess $process) => $this->isBinary($process, 'mysqldump')
            && collect($process->command)->every(fn (string $argument) => ! str_starts_with($argument, '--ignore-table=')));
    }

    public function test_import_requires_typing_confirm(): void
    {
        $this->fakeMysql();
        $this->storeBackup('backup-2026-01-01_000000.zip');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000.zip',
                'confirm' => 'confirm',
            ])
            ->assertSessionHasErrors('confirm');

        Process::assertNothingRan();
    }

    public function test_import_from_a_listed_backup_replaces_database_and_media(): void
    {
        $this->fakeMysql();
        Storage::disk('public')->put('products/1/old.jpg', 'old');
        $this->storeBackup('backup-2026-01-01_000000-media.zip', ['media/products/2/new.jpg' => 'new']);

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000-media.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertRedirect(route('admin.dev-tools.index'))
            ->assertSessionHas('success');

        Process::assertRan(fn (PendingProcess $process) => $this->isBinary($process, 'mysql'));
        Storage::disk('public')->assertMissing('products/1/old.jpg');
        $this->assertSame('new', Storage::disk('public')->get('products/2/new.jpg'));
        $this->assertFalse(app()->isDownForMaintenance());

        $safetyBackups = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_starts_with(basename($path), 'pre-import-'));
        $this->assertCount(1, $safetyBackups);

        // The safety backup must carry the media it is about to overwrite.
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($safetyBackups->first()));
        $this->assertSame('old', $zip->getFromName('media/products/1/old.jpg'));
        $zip->close();
    }

    public function test_import_accepts_an_uploaded_sql_dump(): void
    {
        $this->fakeMysql();
        Storage::disk('public')->put('products/1/keep.jpg', 'keep');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'file' => UploadedFile::fake()->createWithContent('dump.sql', "-- dump\n"),
                'confirm' => 'CONFIRM',
            ])
            ->assertSessionHas('success');

        Process::assertRan(fn (PendingProcess $process) => $this->isBinary($process, 'mysql'));
        Storage::disk('public')->assertExists('products/1/keep.jpg');
    }

    public function test_a_failed_restore_reports_the_error_and_lifts_maintenance_mode(): void
    {
        Process::fake(['*' => function (PendingProcess $process) {
            if ($this->isBinary($process, 'mysql')) {
                return Process::result(errorOutput: 'ERROR 1064: syntax error', exitCode: 1);
            }

            $this->writeFakeDump($process);

            return Process::result();
        }]);
        $this->storeBackup('backup-2026-01-01_000000.zip');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertRedirect(route('admin.dev-tools.index'))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'ERROR 1064')
                && str_contains($message, 'pre-import-'));

        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_the_safety_backup_is_taken_while_the_site_is_down(): void
    {
        $downDuringDump = null;
        Process::fake(['*' => function (PendingProcess $process) use (&$downDuringDump) {
            if ($this->isBinary($process, 'mysqldump')) {
                $downDuringDump = app()->isDownForMaintenance();
            }

            $this->writeFakeDump($process);

            return Process::result();
        }]);
        $this->storeBackup('backup-2026-01-01_000000.zip');

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertSessionHas('success');

        $this->assertTrue($downDuringDump);
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_restore_never_writes_executable_or_hidden_files_to_media(): void
    {
        $this->fakeMysql();
        $this->storeBackup('backup-2026-01-01_000000-media.zip', [
            'media/products/1/photo.jpg' => 'jpg',
            'media/products/1/shell.php' => '<?php',
            'media/.htaccess' => 'Options +ExecCGI',
        ]);

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000-media.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertSessionHas('success');

        Storage::disk('public')->assertExists('products/1/photo.jpg');
        Storage::disk('public')->assertMissing('products/1/shell.php');
        Storage::disk('public')->assertMissing('.htaccess');
    }

    public function test_a_second_restore_is_refused_while_one_is_running(): void
    {
        $this->fakeMysql();
        $this->storeBackup('backup-2026-01-01_000000.zip');
        $lock = Cache::lock(BackupRestorer::LOCK, 60);
        $lock->get();

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'already running'));

        $lock->release();
        Process::assertNothingRan();
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_backup_names_cannot_escape_the_backups_folder(): void
    {
        $this->fakeMysql();
        $admin = $this->adminWithRole('super-admin');

        $this->actingAs($admin)->get('/admin/dev-tools/backups/..%2F.env')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/dev-tools/backups/..%2F.env')->assertNotFound();

        $this->actingAs($admin)
            ->post(route('admin.dev-tools.import'), ['backup' => '../.env', 'confirm' => 'CONFIRM'])
            ->assertSessionHasErrors('backup');

        Process::assertNothingRan();
    }

    public function test_archives_with_unsafe_paths_are_rejected(): void
    {
        $this->fakeMysql();
        $this->storeBackup('backup-2026-01-01_000000.zip', ['../evil.php' => '<?php']);

        $this->actingAs($this->adminWithRole('super-admin'))
            ->post(route('admin.dev-tools.import'), [
                'backup' => 'backup-2026-01-01_000000.zip',
                'confirm' => 'CONFIRM',
            ])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'unsafe path'));

        Process::assertNotRan(fn (PendingProcess $process) => $this->isBinary($process, 'mysql'));
    }

    public function test_backups_can_be_downloaded_and_deleted(): void
    {
        $this->storeBackup('backup-2026-01-01_000000.zip');
        $admin = $this->adminWithRole('super-admin');

        $this->actingAs($admin)
            ->get(route('admin.dev-tools.download', 'backup-2026-01-01_000000.zip'))
            ->assertOk()
            ->assertDownload('backup-2026-01-01_000000.zip');

        $this->actingAs($admin)
            ->delete(route('admin.dev-tools.destroy', 'backup-2026-01-01_000000.zip'))
            ->assertRedirect(route('admin.dev-tools.index'));

        Storage::disk('local')->assertMissing('backups/backup-2026-01-01_000000.zip');
    }

    public function test_backup_command_creates_a_backup(): void
    {
        $this->fakeMysql();

        $this->artisan('ecommerce:backup', ['--media' => true])->assertSuccessful();

        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    private function adminWithRole(string $role): User
    {
        // fresh() loads is_active's database default, which the admin middleware checks.
        $user = User::factory()->create()->fresh();
        $user->roles()->attach(Role::firstWhere('slug', $role));

        return $user;
    }

    /**
     * Succeeds every mysqldump/mysql call; mysqldump "writes" its --result-file.
     */
    private function fakeMysql(): void
    {
        Process::fake(['*' => function (PendingProcess $process) {
            $this->writeFakeDump($process);

            return Process::result();
        }]);
    }

    private function writeFakeDump(PendingProcess $process): void
    {
        foreach ((array) $process->command as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                file_put_contents(substr($argument, strlen('--result-file=')), "-- fake dump\n");
            }
        }
    }

    private function isBinary(PendingProcess $process, string $binary): bool
    {
        return is_array($process->command)
            && $process->command[0] === $binary
            && ! in_array('--version', $process->command, true);
    }

    /**
     * @param  array<string, string>  $extraEntries
     */
    private function storeBackup(string $name, array $extraEntries = []): void
    {
        $path = Storage::disk('local')->path('backups/'.$name);
        Storage::disk('local')->makeDirectory('backups');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', "-- fixture\n");
        $zip->addFromString('manifest.json', json_encode(['version' => 1, 'includes_media' => $extraEntries !== []]));
        foreach ($extraEntries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }
        $zip->close();
    }

    private function openOnlyBackup(): ZipArchive
    {
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($files[0])));

        return $zip;
    }
}
