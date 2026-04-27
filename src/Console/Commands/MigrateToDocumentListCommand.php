<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Meta\AdminCore\Models\PageBlock;

/**
 * One-shot migration: collapse `links`-type blocks (and similar legacy
 * document-list shapes) onto the canonical `document-list` block type.
 *
 *   php artisan admin-core:migrate-to-document-list          # apply
 *   php artisan admin-core:migrate-to-document-list --dry    # preview
 *
 * Safe to re-run: the command skips rows whose block_type is already
 * `document-list` and never overwrites unrecognised data shapes. Each
 * row either migrates cleanly or is flagged in the report.
 *
 * What it does:
 *   - `block_type = 'links'` → `'document-list'`
 *   - moves `data.links[]` → `data.items[]` (preserving item order and
 *     every per-item field — icon, color, title, description, url)
 *   - normalises `data.layout`:
 *       missing      → 'list'
 *       'grid'       → 'grid-3'
 *       recognised   → left as-is
 *
 * Intentionally NOT touched:
 *   - `block_type='content'` with `data.type='grid'` — each consumer
 *     site has its own nested schema (cards[].links[], cards[].features,
 *     …). Those need per-page visual verification, not mass rewrite.
 *   - `block_type` in {downloadable-docs, admission-documents,
 *     accreditation-documents} — legacy schemas are still catalogued
 *     and render. Migrate those per-page if needed.
 */
class MigrateToDocumentListCommand extends Command
{
    protected $signature = 'admin-core:migrate-to-document-list
                            {--dry : preview only, no writes}';

    protected $description = 'Collapse legacy `links` blocks onto the canonical document-list type';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $blocks = PageBlock::query()->where('block_type', 'links')->get();

        if ($blocks->isEmpty()) {
            $this->info('Нет блоков типа `links` для миграции.');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s %d блок(ов) типа `links` → `document-list`',
            $dry ? 'Dry-run:' : 'Migrate:',
            $blocks->count()
        ));

        $migrated = 0;
        $skipped  = 0;

        DB::beginTransaction();
        try {
            foreach ($blocks as $block) {
                $data = is_array($block->data) ? $block->data : (json_decode((string) $block->data, true) ?: []);

                // Already migrated (no-op safety)
                if (isset($data['items']) && !isset($data['links'])) {
                    $this->line("  · #{$block->id} {$block->page_name}/{$block->block_key} — already migrated, only retype");
                    if (!$dry) {
                        $block->block_type = 'document-list';
                        $block->save();
                    }
                    $migrated++;
                    continue;
                }

                // Standard links → items rename
                $items = $data['links'] ?? [];
                if (!is_array($items)) {
                    $this->warn("  ! #{$block->id} {$block->page_name}/{$block->block_key} — data.links not an array, skipped");
                    $skipped++;
                    continue;
                }

                $layout = $data['layout'] ?? 'list';
                $layout = match (strtolower((string) $layout)) {
                    'grid'   => 'grid-3',
                    ''       => 'list',
                    default  => $layout,
                };

                $newData = array_merge($data, ['items' => $items, 'layout' => $layout]);
                unset($newData['links']);

                $this->line("  · #{$block->id} {$block->page_name}/{$block->block_key} — " . count($items) . " item(s), layout={$layout}");

                if (!$dry) {
                    $block->block_type = 'document-list';
                    $block->data       = $newData;
                    $block->save();
                }

                $migrated++;
            }

            if ($dry) {
                DB::rollBack();
                $this->warn("Dry-run: откат транзакции, БД не изменена.");
            } else {
                DB::commit();
                $this->info("✓ Мигрировано: {$migrated}, пропущено: {$skipped}.");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Ошибка миграции: {$e->getMessage()}");
            return self::FAILURE;
        }

        if (!$dry && $migrated > 0) {
            $this->checkConsumerViews();
        }

        return self::SUCCESS;
    }

    /**
     * Smoke-check that the consumer site's blade switch + partial know
     * about `document-list`. After this command renames the block_type,
     * any view that only handled `links` will silently fall through to
     * @default and render an empty container — exactly the regression
     * that hit ETU/meta.edu.kz on 2026-04-22 (commit b17d1c6b).
     *
     * We only WARN, never auto-edit consumer code: every site has its
     * own switch (5128 lines on etec, 386 on ETU) and a generic patcher
     * would be brittle. The warning ships a copy-paste diff instead.
     */
    protected function checkConsumerViews(): void
    {
        $switchPath  = function_exists('resource_path')
            ? resource_path('views/components/page-blocks.blade.php')
            : null;
        $partialPath = function_exists('resource_path')
            ? resource_path('views/components/page-blocks/types/links.blade.php')
            : null;

        $issues = [];

        if ($switchPath && is_file($switchPath)) {
            $contents = (string) file_get_contents($switchPath);
            $hasLinksCase = preg_match("/@case\\('links'\\)/", $contents);
            $hasDocListCase = preg_match("/@case\\('document-list'\\)/", $contents);
            if ($hasLinksCase && !$hasDocListCase) {
                $issues[] = [
                    'file' => $switchPath,
                    'msg' => "В switch есть @case('links') но нет @case('document-list') — мигрированные блоки попадут в @default и не отрендерятся.",
                    'patch' => "  @case('links')\n+ @case('document-list')\n      @include('components.page-blocks.types.links', ...)\n      @break",
                ];
            }
        }

        if ($partialPath && is_file($partialPath)) {
            $contents = (string) file_get_contents($partialPath);
            $hasItemsFallback = str_contains($contents, '$block->items')
                || str_contains($contents, '->items ??')
                || str_contains($contents, 'items ?? ');
            if (!$hasItemsFallback) {
                $issues[] = [
                    'file' => $partialPath,
                    'msg' => "Партиал читает только \$block->links, а после миграции данные лежат в \$block->items. Старые links-блоки продолжат работать, мигрированные — нет.",
                    'patch' => "- \$links = \$block->links ?? [];\n+ \$links = \$block->links ?? \$block->items ?? [];\n+ // также: layout 'grid-N' нужно нормализовать в 'grid' если switch по layout есть",
                ];
            }
        }

        if (empty($issues)) {
            return;
        }

        $this->newLine();
        $this->warn(str_repeat('━', 70));
        $this->warn(' ВНИМАНИЕ: consumer-views не готовы к новому типу `document-list`.');
        $this->warn(' Без правок ниже мигрированные блоки молча перестанут рендериться.');
        $this->warn(str_repeat('━', 70));

        foreach ($issues as $i) {
            $this->newLine();
            $this->line(" <fg=yellow>{$i['file']}</>");
            $this->line("   {$i['msg']}");
            $this->line('');
            foreach (explode("\n", $i['patch']) as $line) {
                $this->line('     ' . $line);
            }
        }

        $this->newLine();
        $this->line(' Подробности — docs/MIGRATING-DOCUMENT-LIST.md в пакете.');
        $this->newLine();
    }
}
