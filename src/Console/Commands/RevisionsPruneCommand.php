<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep only the latest N revisions per entity, deleting older ones. The
 * Revisions feature (Revisionable concern) accumulates rows forever
 * otherwise. DB-agnostic (LENGTH() works on pgsql/mysql/sqlite).
 */
class RevisionsPruneCommand extends Command
{
    protected $signature = 'admin-core:revisions-prune
                            {--keep=10 : Сколько последних ревизий оставить на сущность}
                            {--dry-run : Только показать, что удалится}';

    protected $description = 'Удаляет старые ревизии, оставляя последние N на каждую сущность';

    public function handle(): int
    {
        if (! Schema::hasTable('revisions')) {
            $this->warn('Таблицы revisions нет — нечего чистить.');

            return self::SUCCESS;
        }

        $keep = max(1, (int) $this->option('keep'));
        $dry = (bool) $this->option('dry-run');

        $groups = DB::table('revisions')
            ->select('revisionable_type', 'revisionable_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('revisionable_type', 'revisionable_id')
            ->having('cnt', '>', $keep)
            ->get();

        if ($groups->isEmpty()) {
            $this->info("Нечего чистить — всё укладывается в {$keep} ревизий.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $bytes = 0;

        foreach ($groups as $g) {
            $oldIds = DB::table('revisions')
                ->where('revisionable_type', $g->revisionable_type)
                ->where('revisionable_id', $g->revisionable_id)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->skip($keep)->take(PHP_INT_MAX)
                ->pluck('id');

            if ($oldIds->isEmpty()) {
                continue;
            }

            $bytes += (int) DB::table('revisions')->whereIn('id', $oldIds)->sum(DB::raw('LENGTH(data)'));
            $deleted += $oldIds->count();
            $this->line(sprintf('  %s #%s: -%d', class_basename($g->revisionable_type), $g->revisionable_id, $oldIds->count()));

            if (! $dry) {
                DB::table('revisions')->whereIn('id', $oldIds)->delete();
            }
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Удалено: {$deleted} ревизий, освобождено ~" . round($bytes / 1048576, 2) . ' MB');

        return self::SUCCESS;
    }
}
