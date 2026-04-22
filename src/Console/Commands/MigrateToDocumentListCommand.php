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

        return self::SUCCESS;
    }
}
