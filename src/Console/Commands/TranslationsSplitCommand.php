<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Meta\AdminCore\AdminCore;

/**
 * Seed independent per-locale translation rows for every registered
 * resource with translatable fields.
 *
 * Why: while a record has no translation rows, every locale is served from
 * the shared base column (the Translatable trait's fallback). The FIRST save
 * through the admin form then silently snapshots the form's per-tab values
 * into three independent copies — an editor who changed one tab and checks
 * the site in another language concludes "my edit didn't apply". Running
 * this once makes the split explicit and predictable: each locale gets a row
 * with its CURRENT effective value (locale → kk → ru → base column), so
 * nothing changes visually and from then on every locale is edited on its
 * own tab.
 *
 * Idempotent: existing non-empty translation rows are never touched; re-runs
 * only fill locales/fields that are still missing.
 */
class TranslationsSplitCommand extends Command
{
    protected $signature = 'admin-core:translations-split
                            {--dry-run : Показать, что будет создано, без записи}
                            {--resource=all : all|<имя ресурса> из AdminCore::resource()}';

    protected $description = 'Создаёт недостающие per-locale строки переводов у translatable-ресурсов (снимок текущих эффективных значений)';

    public function handle(AdminCore $core): int
    {
        $dry = (bool) $this->option('dry-run');
        $only = (string) $this->option('resource');
        $locales = (array) config('admin-core.locales', ['ru', 'kk', 'en']);

        $recordsTouched = 0;
        $rowsCreated = 0;

        foreach ($core->getResources() as $name => $config) {
            if ($only !== 'all' && $only !== $name) {
                continue;
            }

            $fields = array_values((array) ($config['translatable'] ?? []));
            $model = $config['model'] ?? null;
            if (! $fields || ! $model || ! class_exists($model)) {
                continue;
            }

            // Duck-typed against the Translatable trait (site apps may ship
            // their own fork — same public API).
            foreach (['translations', 'translate', 'saveTranslations'] as $method) {
                if (! method_exists($model, $method)) {
                    $this->warn("-- {$name}: {$model} не имеет {$method}(), пропущен");
                    continue 2;
                }
            }

            $this->line("-- {$name} ({$model}) --");

            // Soft-deleted records are split too: a restore would otherwise
            // reintroduce the first-save snapshot surprise.
            $query = method_exists($model, 'withTrashed') ? $model::withTrashed() : $model::query();

            $query->with('translations')->chunkById(100, function ($rows) use ($fields, $locales, $dry, &$recordsTouched, &$rowsCreated) {
                foreach ($rows as $m) {
                    $have = [];
                    foreach ($m->translations as $t) {
                        if ($t->value !== null && $t->value !== '') {
                            $have["{$t->locale}.{$t->field}"] = true;
                        }
                    }

                    $created = [];
                    foreach ($locales as $locale) {
                        $payload = [];
                        foreach ($fields as $field) {
                            if (isset($have["{$locale}.{$field}"])) {
                                continue;
                            }
                            $value = $m->translate($field, $locale);
                            if (is_string($value) && $value !== '') {
                                $payload[$field] = $value;
                            }
                        }
                        if ($payload) {
                            if (! $dry) {
                                $m->saveTranslations($locale, $payload);
                            }
                            $created[] = $locale . ':' . implode(',', array_keys($payload));
                            $rowsCreated += count($payload);
                        }
                    }

                    if ($created) {
                        $recordsTouched++;
                        $this->line("  #{$m->getKey()} " . implode(' | ', $created));
                    }
                }
            });
        }

        $this->newLine();
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Записей затронуто: {$recordsTouched}, строк переводов создано: {$rowsCreated}");

        return self::SUCCESS;
    }
}
