<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base for the `admin-core:content-*` editor-hygiene commands. Iterates the
 * table → columns map from `admin-core.editor_hygiene.targets`, finds rows
 * whose columns match the subclass `needles()` (SQL LIKE), applies
 * `apply()` per column, and writes back (unless --dry-run). DB-driver
 * agnostic (plain query builder, chunked).
 */
abstract class ContentHygieneCommand extends Command
{
    protected int $rowsTouched = 0;
    protected int $bytesSaved = 0;

    /** LIKE fragments (without the column) that select candidate rows, e.g. ["LIKE '%data:image%'"]. */
    abstract protected function needles(): array;

    /** Transform a single column value. */
    abstract protected function apply(string $value): string;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = (string) $this->option('target');
        $targets = (array) config('admin-core.editor_hygiene.targets', []);

        foreach ($targets as $table => $columns) {
            if ($only !== 'all' && $only !== $table) {
                continue;
            }
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->processTable($table, (array) $columns, $dry);
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Строк обработано: {$this->rowsTouched}, освобождено ~" . round($this->bytesSaved / 1024, 1) . ' KB');

        return self::SUCCESS;
    }

    private function processTable(string $table, array $columns, bool $dry): void
    {
        $columns = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
        if (! $columns) {
            return;
        }
        $this->line("-- {$table} --");

        // LIKE fails outright on json/jsonb columns (PG), so compare a
        // text-cast of every column. These are full-scan '%…%' predicates —
        // no index was ever in play, the cast costs nothing.
        $castTpl = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CAST(%s AS CHAR)',
            default            => 'CAST(%s AS TEXT)',
        };

        $where = [];
        foreach ($columns as $c) {
            $cast = sprintf($castTpl, $c);
            foreach ($this->needles() as $n) {
                $where[] = "{$cast} {$n}";
            }
        }
        $whereSql = '(' . implode(' OR ', $where) . ')';

        DB::table($table)->whereRaw($whereSql)->orderBy('id')->chunkById(50, function ($rows) use ($table, $columns, $dry) {
            foreach ($rows as $row) {
                $updates = [];
                foreach ($columns as $col) {
                    $original = $row->{$col} ?? null;
                    if (! is_string($original) || $original === '') {
                        continue;
                    }
                    $clean = $this->apply($original);
                    if ($clean !== $original) {
                        $updates[$col] = $clean;
                        $this->bytesSaved += max(0, strlen($original) - strlen($clean));
                    }
                }
                if ($updates) {
                    $this->rowsTouched++;
                    if (! $dry) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                    $this->line("  row #{$row->id} " . implode(', ', array_keys($updates)));
                }
            }
        });
    }
}
