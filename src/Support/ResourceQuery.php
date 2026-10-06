<?php

namespace Meta\AdminCore\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Лента записи ресурса с фильтрами: то, что на ETU сделано для новостей,
 * в общем виде. Используется Content API, а Blade-сайт может звать его
 * из своего контроллера:
 *
 *   $q = ResourceQuery::filtered(AdminCore::getResource('news'), $request->query());
 *   $news = $q->paginate(10)->withQueryString();
 *   $facets = ResourceQuery::facets(AdminCore::getResource('news'));
 *
 * Параметры (все необязательные):
 *   q        — поиск по `searchable` полям, в т.ч. в переводах на любом языке;
 *   category — значение колонки категории (или терм Taxable);
 *   year     — год по колонке даты;
 *   sort     — newest (по умолчанию) | oldest.
 *
 * Конфиг ресурса (всё со значениями по умолчанию):
 *   'searchable'     => ['title', 'excerpt'],  // что из этого есть у модели
 *   'date_field'     => 'published_at',        // иначе created_at
 *   'category_field' => 'category',
 */
class ResourceQuery
{
    /** @var array<string, array<string, bool>> */
    private static array $columns = [];

    public static function filtered(array $config, array $params): Builder
    {
        /** @var class-string<Model> $model */
        $model = $config['model'];
        $query = self::published($model::query(), $model);

        $q = mb_substr(trim((string) ($params['q'] ?? '')), 0, 100);
        if ($q !== '') self::search($query, $config, $q);

        $category = trim((string) ($params['category'] ?? ''));
        $catField = self::categoryField($config);
        if ($category !== '' && $catField && !self::usesTaxable($model)) {
            $query->where($catField, $category);
        }

        $date = self::dateField($config);
        $year = (int) ($params['year'] ?? 0);
        if ($date && $year >= 1900 && $year <= 2200) {
            $query->whereYear($date, $year);
        }

        if ($date) {
            $query->orderBy($date, ($params['sort'] ?? '') === 'oldest' ? 'asc' : 'desc')->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Счётчики для панели фильтров: {total, categories: {slug: n}, years: {2026: n}}.
     * Считаются по опубликованным записям без учёта текущих фильтров.
     */
    public static function facets(array $config): array
    {
        $model = $config['model'];
        $base = fn () => self::published($model::query(), $model);

        $out = ['total' => $base()->count(), 'categories' => (object) [], 'years' => (object) []];

        if ($cat = self::categoryField($config)) {
            $out['categories'] = $base()->whereNotNull($cat)->where($cat, '!=', '')
                ->reorder()->select($cat, DB::raw('count(*) as n'))
                ->groupBy($cat)->orderBy($cat)->pluck('n', $cat)
                ->map(fn ($n) => (int) $n);
        }

        if ($date = self::dateField($config)) {
            $expr = self::yearExpression($date);
            $out['years'] = $base()->whereNotNull($date)->reorder()
                ->select(DB::raw("{$expr} as y"), DB::raw('count(*) as n'))
                ->groupBy(DB::raw($expr))->orderByDesc(DB::raw($expr))
                ->pluck('n', 'y')
                ->mapWithKeys(fn ($n, $y) => [(int) $y => (int) $n]);
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /** Опубликованные: скоуп модели `published()`, если есть, иначе по колонкам. */
    public static function published(Builder $query, string $model): Builder
    {
        if (method_exists($model, 'scopePublished')) {
            return $query->published();
        }
        $table = (new $model)->getTable();
        if (self::has($table, 'status')) $query->where('status', 'published');
        if (self::has($table, 'is_published')) $query->where('is_published', true);

        return $query;
    }

    private static function search(Builder $query, array $config, string $q): void
    {
        $model = $config['model'];
        $table = (new $model)->getTable();
        $fields = $config['searchable'] ?? ['title', 'excerpt'];
        $columns = array_values(array_filter($fields, fn ($f) => self::has($table, $f)));
        $translatable = array_values(array_intersect($fields, $config['translatable'] ?? []));
        $hasTranslations = method_exists($model, 'translations') && $translatable;
        if (!$columns && !$hasTranslations) return;

        $like = '%' . addcslashes($q, '%_\\') . '%';
        $driver = DB::connection()->getDriverName();

        // pgsql — ILIKE; MySQL — LIKE и так без регистра (кириллица тоже);
        // SQLite без регистра сравнивает только латиницу, поэтому там
        // сравниваем через mb_strtolower, зарегистрированную в PDO.
        $match = match ($driver) {
            'pgsql'  => fn ($b, $col) => $b->orWhere($col, 'ilike', $like),
            'sqlite' => self::sqliteLower()
                ? fn ($b, $col) => $b->orWhereRaw('admin_core_lower(' . $b->getGrammar()->wrap($col) . ') LIKE ? ESCAPE \'\\\'', [mb_strtolower($like)])
                : fn ($b, $col) => $b->orWhere($col, 'like', $like),
            default  => fn ($b, $col) => $b->orWhere($col, 'like', $like),
        };

        $query->where(function ($w) use ($columns, $hasTranslations, $translatable, $match) {
            foreach ($columns as $c) $match($w, $c);
            if ($hasTranslations) {
                $w->orWhereHas('translations', fn ($t) => $t->whereIn('field', $translatable)
                    ->where(fn ($v) => $match($v, 'value')));
            }
        });
    }

    /** Регистрирует admin_core_lower() в соединении SQLite. */
    private static function sqliteLower(): bool
    {
        $pdo = DB::connection()->getPdo();
        $fn = fn ($v) => $v === null ? null : mb_strtolower((string) $v);

        if (class_exists(\Pdo\Sqlite::class) && $pdo instanceof \Pdo\Sqlite) {
            return $pdo->createFunction('admin_core_lower', $fn, 1);
        }
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            return @$pdo->sqliteCreateFunction('admin_core_lower', $fn, 1);
        }

        return false;
    }

    public static function dateField(array $config): ?string
    {
        $table = (new $config['model'])->getTable();
        foreach (array_filter([$config['date_field'] ?? null, 'published_at', 'created_at']) as $f) {
            if (self::has($table, $f)) return $f;
        }

        return null;
    }

    public static function categoryField(array $config): ?string
    {
        $table = (new $config['model'])->getTable();
        $f = $config['category_field'] ?? 'category';

        return self::has($table, $f) ? $f : null;
    }

    private static function yearExpression(string $column): string
    {
        $c = DB::connection()->getQueryGrammar()->wrap($column);

        return match (DB::connection()->getDriverName()) {
            'pgsql'  => "CAST(EXTRACT(YEAR FROM {$c}) AS INTEGER)",
            'sqlite' => "CAST(strftime('%Y', {$c}) AS INTEGER)",
            'sqlsrv' => "YEAR({$c})",
            default  => "YEAR({$c})",
        };
    }

    private static function usesTaxable(string $model): bool
    {
        return in_array(\Meta\AdminCore\Concerns\Taxable::class, class_uses_recursive($model) ?: [], true);
    }

    private static function has(string $table, string $column): bool
    {
        return self::$columns[$table][$column] ??= Schema::hasColumn($table, $column);
    }
}
