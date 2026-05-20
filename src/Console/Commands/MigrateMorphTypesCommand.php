<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Consolidates morph-typed rows (translations.translatable_type,
 * revisions.revisionable_type, activity_logs.subject_type, …) onto the
 * stable aliases registered by AdminCoreServiceProvider (`page_block`,
 * `menu_item`, etc.).
 *
 * Why this exists:
 * Before v1.3, consumer sites with their own `App\Models\PageBlock`
 * wrote translations as `'App\\Models\\PageBlock'` while admin-core
 * wrote `'Meta\\AdminCore\\Models\\PageBlock'`. Both pointed at the
 * same database row, but neither side saw the other side's writes.
 *
 * After v1.3 both sides write the alias `'page_block'`. Existing
 * rows still hold the FQCN values — this command rewrites them.
 *
 *   php artisan core:migrate-morph-types          — dry-run, prints counts
 *   php artisan core:migrate-morph-types --apply  — actually update
 */
class MigrateMorphTypesCommand extends Command
{
    protected $signature = 'core:migrate-morph-types
                            {--apply : Actually write the UPDATEs (otherwise dry-run)}';

    protected $description = 'Migrate FQCN morph types in translations/revisions/activity_logs to stable aliases (page_block, menu_item, setting, lead).';

    /**
     * Tables with their morph-type column. Add to this list if your
     * site has more polymorphic tables.
     */
    protected array $tables = [
        'translations'   => 'translatable_type',
        'revisions'      => 'revisionable_type',
        'activity_logs'  => 'subject_type',
        'media'          => 'model_type',
    ];

    /**
     * Admin-core's own classes that should always collapse into the
     * paired alias, in addition to whatever the consumer has in their
     * Relation::morphMap(). Lets consumer-overridden aliases still
     * scoop up admin-core's legacy FQCN rows.
     */
    protected array $adminCoreAliases = [
        'page_block' => [\Meta\AdminCore\Models\PageBlock::class],
        'menu_item'  => [\Meta\AdminCore\Models\MenuItem::class],
        'setting'    => [\Meta\AdminCore\Models\Setting::class],
        'lead'       => [\Meta\AdminCore\Models\Lead::class],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $map   = Relation::morphMap();

        if (empty($map)) {
            $this->error('No morph aliases registered. Make sure AdminCoreServiceProvider has booted.');
            return self::FAILURE;
        }

        // alias → [FQCN classes to merge into it]
        $byAlias = [];
        foreach ($map as $alias => $class) {
            $byAlias[$alias][] = $class;
        }
        foreach ($this->adminCoreAliases as $alias => $classes) {
            foreach ($classes as $class) {
                if (! isset($byAlias[$alias]) || ! in_array($class, $byAlias[$alias], true)) {
                    $byAlias[$alias][] = $class;
                }
            }
        }

        $this->info(sprintf('%s mode. %d aliases.', $apply ? 'APPLY' : 'DRY-RUN', count($byAlias)));
        foreach ($byAlias as $alias => $classes) {
            $this->line("  {$alias} ← " . implode(', ', $classes));
        }
        $this->newLine();

        $touched = 0;

        foreach ($this->tables as $table => $col) {
            if (! \Schema::hasTable($table) || ! \Schema::hasColumn($table, $col)) {
                continue;
            }

            $this->line("Table <fg=cyan>{$table}.{$col}</>:");

            foreach ($byAlias as $alias => $classes) {
                foreach ($classes as $class) {
                    if ($class === $alias) {
                        continue;
                    }
                    $count = DB::table($table)->where($col, $class)->count();
                    if ($count === 0) {
                        continue;
                    }
                    $this->line("  {$count} rows with '{$class}' → '{$alias}'");

                    if (! $apply) {
                        continue;
                    }

                    // Some tables have a unique index that includes the
                    // morph-type column (translations: type+id+locale+field).
                    // Bulk UPDATE collides when an alias row already
                    // exists. Process row-by-row, prefer the newer
                    // (alias) row when there's a duplicate.
                    DB::table($table)->where($col, $class)->orderBy('id')->chunkById(500, function ($rows) use ($table, $col, $alias, &$touched) {
                        foreach ($rows as $row) {
                            try {
                                DB::table($table)->where('id', $row->id)->update([$col => $alias]);
                                $touched++;
                            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                                // Alias row already covers this morph slot.
                                // Drop the FQCN duplicate.
                                DB::table($table)->where('id', $row->id)->delete();
                            }
                        }
                    });
                }
            }
        }

        $this->newLine();
        if ($apply) {
            $this->info("Done. Updated {$touched} rows total.");
        } else {
            $this->comment('No changes written (dry-run). Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }
}
