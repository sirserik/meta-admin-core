<?php

namespace Meta\AdminCore\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Meta\AdminCore\Services\ImageService;

/**
 * Горизонтальная обложка карточки для записи ресурса.
 *
 * Включается в конфиге ресурса рядом с `image_field`:
 *
 *   'image_field' => 'image',
 *   'card_image'  => true,                                   // → meta_data.card_image, 1600×860
 *   'card_image'  => ['to' => 'card_image', 'width' => 1200, 'height' => 630],
 *
 * `to` — колонка или путь внутри JSON-колонки через точку (колонка должна
 * быть приведена к array в $casts модели). Снимок страницы остаётся как
 * есть, карточка ленты берёт обложку. Пересобирается, когда меняется
 * исходник или обложки ещё нет.
 */
class CardImage
{
    public static function options(array $config): ?array
    {
        $card = $config['card_image'] ?? null;
        if (!$card || empty($config['image_field'])) return null;

        $card = is_array($card) ? $card : [];

        return [
            'from'   => $config['image_field'],
            'to'     => $card['to'] ?? 'meta_data.card_image',
            'width'  => (int) ($card['width'] ?? 1600),
            'height' => (int) ($card['height'] ?? 860),
        ];
    }

    /** Текущее значение обложки у записи. */
    public static function current(Model $m, array $opt): ?string
    {
        [$col, $rest] = array_pad(explode('.', $opt['to'], 2), 2, null);
        $value = $m->{$col};
        if ($rest === null) return $value ?: null;
        if (is_string($value)) $value = json_decode($value, true);

        return data_get((array) $value, $rest) ?: null;
    }

    /**
     * Собирает обложку, если она нужна. Возвращает новый путь или null,
     * если ничего не менялось. Запись сохраняется без событий и ревизий.
     */
    public static function sync(Model $m, array $opt, bool $force = false): ?string
    {
        $source = $m->{$opt['from']};
        if (!$source) return null;

        $current = self::current($m, $opt);
        $sourceChanged = $m->wasRecentlyCreated || $m->wasChanged($opt['from']);
        if (!$force && $current && !$sourceChanged) return null;

        $path = app(ImageService::class)->makeCard($source, $opt['width'], $opt['height']);
        if (!$path) return null;

        // Прежняя автоматическая обложка от другого исходника больше не нужна.
        if ($current && $current !== $path && str_ends_with($current, '-wide.jpg')) {
            Storage::disk('public')->delete(ltrim(preg_replace('#^/?(storage/|media/)+#', '', $current), '/'));
        }

        [$col, $rest] = array_pad(explode('.', $opt['to'], 2), 2, null);
        if ($rest === null) {
            $m->{$col} = $path;
        } else {
            $value = $m->{$col};
            if (is_string($value)) $value = json_decode($value, true);
            $value = (array) ($value ?? []);
            data_set($value, $rest, $path);
            $m->{$col} = $value;
        }
        $m->saveQuietly();

        return $path;
    }
}
