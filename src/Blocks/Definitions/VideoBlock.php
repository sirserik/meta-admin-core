<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class VideoBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'video';
    }

    public function label(): string
    {
        return 'Video — встроенное видео';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'video';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'description' => ['type' => 'textarea', 'label' => 'Описание', 'translatable' => true],
            'source' => [
                'type' => 'select',
                'label' => 'Источник',
                'default' => 'youtube',
                'options' => ['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'direct' => 'Прямая ссылка'],
            ],
            'video_id' => ['type' => 'text', 'label' => 'ID видео / URL', 'required' => true, 'help' => 'YouTube: dQw4w9WgXcQ; Vimeo: 76979871; Direct: полный URL .mp4'],
            'thumbnail' => ['type' => 'media', 'label' => 'Превью (опционально)'],
        ];
    }

    public function render(array $data, string $locale): string
    {
        $src = $data['source'] ?? 'youtube';
        $vid = $data['video_id'] ?? '';
        $embedUrl = match ($src) {
            'youtube' => str_starts_with($vid, 'http') ? $vid : "https://www.youtube.com/embed/{$vid}",
            'vimeo' => str_starts_with($vid, 'http') ? $vid : "https://player.vimeo.com/video/{$vid}",
            default => $vid,
        };

        return View::make('blocks.v2.video', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'description' => $this->localized($data['description'] ?? null, $locale),
            'source' => $src,
            'embedUrl' => $embedUrl,
            'thumbnail' => $data['thumbnail'] ?? null,
        ])->render();
    }
}
