<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    /**
     * Tables backed up in this order. Restore uses the same order.
     * FK-producing tables come before FK-consuming tables where possible.
     * The lesson_plan_families ↔ lesson_plan_versions circular FK is handled
     * by disabling FK checks during restore.
     */
    private const TABLES = [
        // Spatie permission (no app FKs)
        'roles',
        'permissions',
        'role_has_permissions',
        // Core app
        'users',
        'model_has_roles',
        'model_has_permissions',
        'subjects',
        'subject_grades',
        'subject_grade_user',
        // Lesson plans (circular FK — handled by disabling FK checks)
        'lesson_plan_families',
        'lesson_plan_versions',
        'favorites',
        'messages',
        'deletion_requests',
        // AI
        'agent_conversations',
        'agent_conversation_messages',
    ];

    private const BACKUP_DISK = 'local';

    private const BACKUP_DIR = 'backups';

    /**
     * Create a backup JSON file.
     *
     * Returns the filename and a per-table row count so callers can confirm
     * what was captured without having to re-read the file.
     *
     * Note: set_time_limit() may be a no-op on DreamHost shared hosting if
     * disable_functions restricts it. The .htaccess php_value max_execution_time
     * directive is the primary safeguard there.
     *
     * @return array{filename: string, counts: array<string, int>}
     */
    public function create(): array
    {
        set_time_limit(120);

        $now = now();

        $payload = [
            'created_at' => $now->toIso8601String(),
            'app_version' => config('app.version', '1.0'),
            'tables' => [],
        ];

        /** @var array<string, int> $counts */
        $counts = [];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $payload['tables'][$table] = $rows;
            $counts[$table] = count($rows);
        }

        $filename = 'backup_'.$now->format('Y-m-d_His').'.json';
        $path = self::BACKUP_DIR.'/'.$filename;

        Storage::disk(self::BACKUP_DISK)->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['filename' => $filename, 'counts' => $counts];
    }

    /**
     * Restore from a named backup file.
     * All app tables are truncated and repopulated. FK checks are disabled
     * to handle the lesson_plan_families ↔ lesson_plan_versions circular FK.
     *
     * Warning: this assumes the current schema matches the backup schema. Restoring
     * a backup from a different migration state (e.g. before a column was added) may
     * cause silent data corruption or insert failures.
     *
     * Note: set_time_limit() may be a no-op on DreamHost shared hosting if
     * disable_functions restricts it.
     */
    public function restore(string $filename): void
    {
        set_time_limit(120);

        // Strip any directory components to prevent path traversal
        $filename = basename($filename);
        $path = self::BACKUP_DIR.'/'.$filename;

        $json = Storage::disk(self::BACKUP_DISK)->get($path)
            ?? throw new RuntimeException("Backup file not found: {$filename}");

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid backup file: JSON could not be decoded.');
        }

        if (! isset($payload['tables'])) {
            throw new RuntimeException('Invalid backup file: missing tables key.');
        }

        $driver = DB::getDriverName();

        // setForeignKeys() must be called outside the transaction on SQLite.
        // SQLite silently ignores PRAGMA foreign_keys changes issued inside a
        // BEGIN … COMMIT block. MariaDB's SET FOREIGN_KEY_CHECKS works either way,
        // but keeping both calls outside is consistent and safe for both drivers.
        $this->setForeignKeys($driver, false);

        try {
            DB::transaction(function () use ($payload) {
                // Use delete() not truncate(). TRUNCATE TABLE on MariaDB/InnoDB
                // issues an implicit commit even inside an explicit transaction,
                // causing PDO::commit() to throw "There is no active transaction".
                // DELETE FROM is fully transactional and avoids this.
                foreach (array_reverse(self::TABLES) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                foreach (self::TABLES as $table) {
                    if (empty($payload['tables'][$table] ?? [])) {
                        continue;
                    }

                    foreach (array_chunk($payload['tables'][$table], 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            });
        } finally {
            // Always re-enable FK checks — even if the transaction throws
            $this->setForeignKeys($driver, true);
        }
    }

    /**
     * List available backups, newest first.
     *
     * @return array<int, array{filename: string, created_at: int, size: int}>
     */
    public function list(): array
    {
        // Storage::files() returns paths prefixed with the directory, so basename()
        // is used to get the bare filename for display and filtering.
        $files = Storage::disk(self::BACKUP_DISK)->files(self::BACKUP_DIR);

        $backups = [];

        foreach ($files as $file) {
            $filename = basename($file);

            if (! str_starts_with($filename, 'backup_') || ! str_ends_with($filename, '.json')) {
                continue;
            }

            $backups[] = [
                'filename' => $filename,
                'created_at' => Storage::disk(self::BACKUP_DISK)->lastModified($file),
                'size' => Storage::disk(self::BACKUP_DISK)->size($file),
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    private function setForeignKeys(string $driver, bool $enabled): void
    {
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = '.($enabled ? 'ON' : 'OFF'));
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS='.($enabled ? '1' : '0'));
        }
    }
}
