<?php

use App\Filament\App\Pages\AdminDashboard;
use App\Models\Subject;
use App\Models\User;
use App\Services\BackupService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
});

// ── BackupService::create() ────────────────────────────────────────────────────

test('create() writes a JSON backup file to storage', function () {
    $filename = app(BackupService::class)->create();

    expect($filename)->toStartWith('backup_')->toEndWith('.json');
    Storage::disk('local')->assertExists('backups/'.$filename);
});

test('create() backup contains expected top-level keys', function () {
    $filename = app(BackupService::class)->create();

    $json = json_decode(Storage::disk('local')->get('backups/'.$filename), true);

    expect($json)->toHaveKey('created_at')
        ->toHaveKey('tables')
        ->toHaveKey('tables.users');
});

test('create() captures existing records in backup', function () {
    $user = User::factory()->create(['name' => 'Backup Test User']);

    $filename = app(BackupService::class)->create();

    $json = json_decode(Storage::disk('local')->get('backups/'.$filename), true);

    $names = collect($json['tables']['users'])->pluck('name')->all();
    expect($names)->toContain('Backup Test User');
});

// ── BackupService::list() ──────────────────────────────────────────────────────

test('list() returns an empty array when no backups exist', function () {
    expect(app(BackupService::class)->list())->toBeEmpty();
});

test('list() returns backup entries with filename and size keys', function () {
    app(BackupService::class)->create();

    $list = app(BackupService::class)->list();

    expect($list)->toHaveCount(1);
    expect($list[0])->toHaveKey('filename')->toHaveKey('size')->toHaveKey('created_at');
});

test('list() only returns files matching the backup_ prefix', function () {
    // Place a non-backup file in the directory
    Storage::disk('local')->put('backups/other_file.json', '{}');
    app(BackupService::class)->create();

    $list = app(BackupService::class)->list();

    expect($list)->toHaveCount(1);
    expect($list[0]['filename'])->toStartWith('backup_');
});

// ── BackupService::restore() ───────────────────────────────────────────────────

test('restore() repopulates users from backup', function () {
    $original = User::factory()->create(['name' => 'Restore Me']);

    $filename = app(BackupService::class)->create();

    // Delete the user after backup
    User::where('id', $original->id)->delete();
    expect(User::find($original->id))->toBeNull();

    app(BackupService::class)->restore($filename);

    expect(User::find($original->id))->not->toBeNull();
    expect(User::find($original->id)->name)->toBe('Restore Me');
});

test('restore() repopulates subjects from backup', function () {
    $subject = Subject::factory()->create(['name' => 'Geography']);

    $filename = app(BackupService::class)->create();

    Subject::where('id', $subject->id)->delete();

    app(BackupService::class)->restore($filename);

    expect(Subject::where('name', 'Geography')->exists())->toBeTrue();
});

test('restore() throws RuntimeException for a missing file', function () {
    expect(fn () => app(BackupService::class)->restore('nonexistent_backup.json'))
        ->toThrow(RuntimeException::class);
});

// ── AdminDashboard Livewire actions ───────────────────────────────────────────

test('backupNow action creates a backup and sends a success notification', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->call('backupNow')
        ->assertNotified();

    expect(app(BackupService::class)->list())->toHaveCount(1);
});

test('restoreBackup action sends a warning when no filename is selected', function () {
    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('restoreFilename', '')
        ->call('restoreBackup')
        ->assertNotified();

    // No restore occurred — storage should still be empty
    expect(app(BackupService::class)->list())->toBeEmpty();
});

test('getAvailableBackups returns empty array when no backups exist', function () {
    $this->actingAs(makeSiteAdmin());

    $component = Livewire::test(AdminDashboard::class);

    expect($component->instance()->getAvailableBackups())->toBeEmpty();
});

test('getAvailableBackups returns entries after a backup is created', function () {
    $this->actingAs(makeSiteAdmin());

    app(BackupService::class)->create();

    $component = Livewire::test(AdminDashboard::class);

    expect($component->instance()->getAvailableBackups())->toHaveCount(1);
});

test('restoreBackup action redirects to login after successful restore', function () {
    // Mock BackupService so the actual DB restore doesn't run — session()
    // invalidation during a real restore corrupts the Livewire test harness.
    // BackupService::restore() correctness is proven by the unit tests above.
    $filename = 'backup_2026-01-01_120000.json';

    $mock = $this->mock(BackupService::class);
    $mock->shouldReceive('restore')->once()->with($filename);
    $mock->shouldReceive('list')->andReturn([]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('restoreFilename', $filename)
        ->call('restoreBackup')
        ->assertRedirect(route('filament.app.auth.login'));
});

test('restoreBackup action sends a danger notification when restore throws', function () {
    $mock = $this->mock(BackupService::class);
    $mock->shouldReceive('restore')->andThrow(new RuntimeException('Restore failed.'));
    $mock->shouldReceive('list')->andReturn([]);

    $this->actingAs(makeSiteAdmin());

    Livewire::test(AdminDashboard::class)
        ->set('restoreFilename', 'backup_2026-01-01_120000.json')
        ->call('restoreBackup')
        ->assertNotified();
});

test('restoreBackup action rejects path traversal filenames gracefully', function () {
    $this->actingAs(makeSiteAdmin());

    // basename() in BackupService::restore() strips the path component, turning
    // "../../../nonexistent.json" into "nonexistent.json". Storage::get() returns
    // null for a non-existent file, which throws RuntimeException — caught as
    // a danger notification, not an unhandled crash.
    Livewire::test(AdminDashboard::class)
        ->set('restoreFilename', '../../../nonexistent.json')
        ->call('restoreBackup')
        ->assertNotified();
});
