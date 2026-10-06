<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Где на сайте упоминается человек или фраза — по ВСЕМ текстовым колонкам
 * всех таблиц: карточки, переводы, HTML блоков и новостей, JSON, отключённые
 * блоки. Поиск по одной таблице сотрудников мало: имена сидят в тексте.
 *
 *   php artisan admin-core:find-mentions "Баетова" "Baetova" "Баетовой"
 *   php artisan admin-core:find-mentions "Бахыт Е" --table=translations --table=page_blocks
 *
 * Только чтение. Регистр не важен; в JSON ищется и \u-экранированная запись
 * (так Laravel кладёт кириллицу в json-колонки MySQL/SQLite).
 */
class FindMentionsCommand extends Command
{
    protected $signature = 'admin-core:find-mentions
                            {needle* : Что искать (несколько вариантов написания — несколько аргументов)}
                            {--table=* : Только эти таблицы}
                            {--limit=20 : Сколько совпадений показывать на колонку}';

    protected $description = 'Найти упоминания человека/фразы во всех текстовых колонках базы (только чтение)';

    private const TEXT_TYPES = ['char', 'varchar', 'character varying', 'character', 'text', 'tinytext',
        'mediumtext', 'longtext', 'json', 'jsonb', 'string', 'bpchar', 'citext', 'clob'];

    private const SKIP_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens', 'personal_access_tokens'];

    public function handle(): int
    {
        $needles = array_values(array_filter(array_map('trim', (array) $this->argument('needle')), 'strlen'));
        $variants = $this->variants($needles);
        $driver = DB::connection()->getDriverName();
        $only = (array) $this->option('table');
        $limit = max(1, (int) $this->option('limit'));

        $tables = collect(Schema::getTables())->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP_TABLES, true))
            ->when($only, fn ($c) => $c->filter(fn ($t) => in_array($t, $only, true)))
            ->sort()->values();

        $hits = 0;
        $columnsScanned = 0;
        foreach ($tables as $table) {
            $columns = collect(Schema::getColumns($table));
            $key = $columns->firstWhere('name', 'id') ? 'id' : null;
            $text = $columns->filter(fn ($c) => $this->isText($c))->pluck('name');

            foreach ($text as $col) {
                $columnsScanned++;
                foreach ($this->matches($table, $col, $key, $variants, $driver, $limit) as [$id, $value]) {
                    $hits++;
                    $this->line(sprintf('<info>%s</info>%s.<comment>%s</comment>  %s',
                        $table, $key ? "#{$id}" : '', $col, $this->snippet((string) $value, $needles)));
                }
            }
        }

        $this->newLine();
        $this->line("Проверено таблиц: {$tables->count()}, текстовых колонок: {$columnsScanned}. Совпадений: {$hits}.");

        return self::SUCCESS;
    }

    /** Варианты поиска: как есть + JSON-экранированная запись для не-ASCII. */
    private function variants(array $needles): array
    {
        $out = [];
        foreach ($needles as $n) {
            $out[] = $n;
            $escaped = trim(json_encode($n), '"');
            if ($escaped !== $n) $out[] = $escaped;
        }

        return array_values(array_unique($out));
    }

    private function isText(array $column): bool
    {
        $type = strtolower((string) ($column['type_name'] ?? $column['type'] ?? ''));
        $type = preg_replace('/\(.*$/', '', $type);

        return in_array($type, self::TEXT_TYPES, true);
    }

    /** @return iterable<array{0:mixed,1:string}> */
    private function matches(string $table, string $col, ?string $key, array $variants, string $driver, int $limit): iterable
    {
        $select = array_filter([$key, $col]);

        // SQLite сравнивает без регистра только латиницу — кириллицу сверяем в PHP.
        if ($driver === 'sqlite') {
            $found = 0;
            foreach (DB::table($table)->select($select)->whereNotNull($col)->cursor() as $row) {
                $value = (string) $row->{$col};
                foreach ($variants as $v) {
                    if (mb_stripos($value, $v) !== false) {
                        yield [$key ? $row->{$key} : null, $value];
                        if (++$found >= $limit) return;
                        break;
                    }
                }
            }

            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $wrapped = $grammar->wrap($col);
        $q = DB::table($table)->select($select)->where(function ($w) use ($variants, $wrapped, $driver) {
            foreach ($variants as $v) {
                $like = '%' . addcslashes($v, '%_\\') . '%';
                $driver === 'pgsql'
                    ? $w->orWhereRaw("{$wrapped}::text ILIKE ?", [$like])
                    : $w->orWhereRaw("LOWER({$wrapped}) LIKE LOWER(?)", [$like]);
            }
        })->limit($limit);

        foreach ($q->get() as $row) {
            yield [$key ? $row->{$key} : null, (string) $row->{$col}];
        }
    }

    private function snippet(string $value, array $needles): string
    {
        // JSON с \u-экранированием — показываем человеческим текстом.
        if (str_contains($value, '\\u') && is_array($decoded = json_decode($value, true))) {
            $value = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value)));
        foreach ($needles as $n) {
            $pos = mb_stripos($value, $n);
            if ($pos !== false) {
                $start = max(0, $pos - 50);
                $cut = mb_substr($value, $start, mb_strlen($n) + 100);

                return ($start > 0 ? '…' : '') . $cut . (mb_strlen($value) > $start + mb_strlen($n) + 100 ? '…' : '');
            }
        }

        return mb_substr($value, 0, 120) . (mb_strlen($value) > 120 ? '…' : '') . '  (в экранированном JSON)';
    }
}
