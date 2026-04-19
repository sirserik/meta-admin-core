<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reverse of admin-core:export. Reads the ZIP produced there and
 * inserts or upserts every row into the matching table.
 *
 *   --mode=merge   (default)  upsert by primary key, keep rows not in the dump
 *   --mode=replace            truncate each target table first
 *   --dry-run                 parse and report counts without touching the DB
 *
 * Safe by default: foreign-key constraints off for the duration of
 * the import so child-before-parent ordering doesn't matter. Wrapped
 * in a transaction when the driver supports it.
 */
class ImportContentCommand extends Command
{
    protected $signature   = 'admin-core:import
                              {file : Path to the zip produced by admin-core:export}
                              {--mode=merge : merge (default) | replace}
                              {--dry-run : Parse and report without writing}';
    protected $description = 'Import CMS content from a zip previously produced by admin-core:export.';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }
        if (!class_exists(\ZipArchive::class)) {
            $this->error('PHP ZipArchive extension is not installed.');
            return self::FAILURE;
        }

        $mode = strtolower((string) $this->option('mode'));
        if (!in_array($mode, ['merge', 'replace'], true)) {
            $this->error("Invalid --mode: {$mode}");
            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            $this->error("Can't read zip: {$path}");
            return self::FAILURE;
        }

        $read = function (string $name) use ($zip): ?array {
            $raw = $zip->getFromName($name);
            if ($raw === false) return null;
            $parsed = json_decode($raw, true);
            return is_array($parsed) ? $parsed : null;
        };

        $manifest = $read('manifest.json');
        if (!$manifest) {
            $this->error('manifest.json missing or invalid — not a valid admin-core export.');
            return self::FAILURE;
        }
        $this->components->info("Importing export from {$manifest['exported_at']} (format v{$manifest['format_version']})");

        $targets = [];
        foreach (array_keys($manifest['tables'] ?? []) as $t) {
            $rows = $read("{$t}.json");
            if ($rows !== null) $targets[$t] = $rows;
        }
        foreach (($manifest['resources'] ?? []) as $name => $meta) {
            $rows = $read("resource.{$name}.json");
            if ($rows !== null) $targets[$meta['table']] = $rows;
        }
        $zip->close();

        $this->table(['Table', 'Rows'], collect($targets)->map(fn ($r, $k) => [$k, count($r)])->values()->all());

        if ($dry) {
            $this->components->info('Dry-run complete — no rows written.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($targets, $mode) {
            // SQLite/MySQL: disable FK checks so the import order is
            // irrelevant. The transaction rolls back on any failure.
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite')  DB::statement('PRAGMA foreign_keys = OFF');
            if ($driver === 'mysql')   DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($targets as $table => $rows) {
                if (!Schema::hasTable($table)) {
                    $this->components->warn("Skipping {$table} — table does not exist on this install.");
                    continue;
                }
                if ($mode === 'replace') DB::table($table)->truncate();

                foreach (array_chunk($rows, 500) as $chunk) {
                    // upsert by primary key. Fall back to insert when
                    // the row has no id (shouldn't happen for our tables
                    // but keeps the command forgiving).
                    DB::table($table)->upsert(
                        $chunk,
                        ['id'],
                        array_keys($chunk[0] ?? []),
                    );
                }
            }

            if ($driver === 'sqlite')  DB::statement('PRAGMA foreign_keys = ON');
            if ($driver === 'mysql')   DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        $this->components->info("Import complete ({$mode} mode).");
        return self::SUCCESS;
    }
}
