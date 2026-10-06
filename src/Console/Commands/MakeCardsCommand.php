<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Meta\AdminCore\Facades\AdminCore;
use Meta\AdminCore\Support\CardImage;

/**
 * Обложки карточек для уже существующих записей ресурса — тех, что
 * заведены до включения `card_image` в конфиге. Новые записи получают
 * обложку при сохранении в админке сами.
 *
 *   php artisan admin-core:make-cards news --dry-run
 *   php artisan admin-core:make-cards news            # только где обложки нет
 *   php artisan admin-core:make-cards news --force    # пересобрать все
 */
class MakeCardsCommand extends Command
{
    protected $signature = 'admin-core:make-cards
                            {resource : Имя ресурса AdminCore (news, articles…)}
                            {--force : Пересобрать и там, где обложка уже есть}
                            {--dry-run : Только показать, что будет сделано}';

    protected $description = 'Собрать горизонтальные обложки карточек (card_image) для существующих записей ресурса';

    public function handle(): int
    {
        $config = AdminCore::getResource((string) $this->argument('resource'));
        $opt = $config ? CardImage::options($config) : null;
        if (!$opt) {
            $this->error('У ресурса нет image_field + card_image в конфиге.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $made = $skipped = $failed = 0;

        $config['model']::query()->whereNotNull($opt['from'])->where($opt['from'], '!=', '')
            ->orderBy('id')->chunkById(50, function ($rows) use ($opt, $force, &$made, &$skipped, &$failed) {
                foreach ($rows as $m) {
                    if (!$force && CardImage::current($m, $opt)) { $skipped++; continue; }
                    if ($this->option('dry-run')) { $this->line("#{$m->id}  {$m->{$opt['from']}}"); $made++; continue; }

                    $path = CardImage::sync($m, $opt, force: true);
                    $path ? $made++ : $failed++;
                    $this->line(($path ? '<info>ok</info>  ' : '<error>нет файла</error>  ') . "#{$m->id}  " . ($path ?? $m->{$opt['from']}));
                }
            });

        $this->newLine();
        $this->line(($this->option('dry-run') ? 'Будет собрано' : 'Собрано') . ": {$made}, пропущено (обложка есть): {$skipped}, без исходника на диске: {$failed}.");

        return self::SUCCESS;
    }
}
