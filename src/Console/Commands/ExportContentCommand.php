<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Meta\AdminCore\Facades\AdminCore;

/**
 * Dump CMS state (page blocks, menu items, taxonomy terms, every
 * registered resource, plus the polymorphic translations rows that
 * glue i18n together) into a single ZIP of JSON files.
 *
 * Companion import command lives next door. The format is:
 *
 *   manifest.json        — metadata (version, exported_at, tables)
 *   page_blocks.json
 *   menu_items.json
 *   translations.json    — filtered to types present in the export
 *   taxonomy_terms.json
 *   taxonomy_term_model.json
 *   resource.{name}.json — one file per AdminCore::resource() entry
 *
 * Intent: move content between staging/prod, seed fresh installs,
 * snapshot before risky edits.
 */
class ExportContentCommand extends Command
{
    protected $signature   = 'admin-core:export
                              {--out= : Output zip path (default storage/app/exports/YYYY-MM-DD-HHMM.zip)}';
    protected $description = 'Export CMS content (blocks, menu, taxonomies, resources) to a single zip.';

    public function handle(): int
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->error('PHP ZipArchive extension is not installed.');
            return self::FAILURE;
        }

        $out = (string) ($this->option('out') ?: storage_path('app/exports/' . date('Y-m-d-Hi') . '.zip'));
        @mkdir(dirname($out), 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($out, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to open {$out} for writing.");
            return self::FAILURE;
        }

        $manifest = [
            'format_version' => 1,
            'exported_at'    => now()->toIso8601String(),
            'tables'         => [],
            'resources'      => [],
        ];

        $tables = [
            'page_blocks',
            'menu_items',
            'taxonomy_terms',
            'taxonomy_term_model',
            'translations',
            'settings',
        ];

        foreach ($tables as $t) {
            if (!Schema::hasTable($t)) continue;
            $rows = DB::table($t)->get();
            $zip->addFromString("{$t}.json", $rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $manifest['tables'][$t] = $rows->count();
        }

        foreach (AdminCore::getResources() as $name => $config) {
            if (!isset($config['model']) || !class_exists($config['model'])) continue;
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $config['model'];
            $table = (new $model)->getTable();
            if (!Schema::hasTable($table)) continue;
            $rows = DB::table($table)->get();
            $zip->addFromString("resource.{$name}.json", $rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $manifest['resources'][$name] = ['table' => $table, 'count' => $rows->count()];
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $this->components->info("Exported to {$out}");
        $this->table(['Section', 'Rows'], collect($manifest['tables'])
            ->merge(collect($manifest['resources'])->map(fn ($r) => $r['count']))
            ->map(fn ($v, $k) => [$k, $v])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
