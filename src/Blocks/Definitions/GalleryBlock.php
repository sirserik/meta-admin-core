<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class GalleryBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'gallery';
    }

    public function label(): string
    {
        return 'Gallery — галерея изображений';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'images';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'layout' => [
                'type' => 'select',
                'label' => 'Раскладка',
                'default' => 'grid',
                'options' => ['grid' => 'Сетка', 'masonry' => 'Масонри', 'carousel' => 'Карусель'],
            ],
            'columns' => [
                'type' => 'select',
                'label' => 'Колонок (для сетки)',
                'default' => '3',
                'options' => ['2' => '2', '3' => '3', '4' => '4'],
            ],
            'images' => [
                'type' => 'repeater',
                'label' => 'Изображения',
                'fields' => [
                    'url' => ['type' => 'media', 'label' => 'Изображение', 'required' => true],
                    'caption' => ['type' => 'text', 'label' => 'Подпись', 'translatable' => true],
                    'alt' => ['type' => 'text', 'label' => 'Alt-текст', 'translatable' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.gallery', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'layout' => $data['layout'] ?? 'grid',
            'columns' => (int) ($data['columns'] ?? 3),
            'images' => collect($data['images'] ?? [])->map(fn ($i) => [
                'url' => $i['url'] ?? '',
                'caption' => $this->localized($i['caption'] ?? null, $locale),
                'alt' => $this->localized($i['alt'] ?? null, $locale) ?: '',
            ])->filter(fn ($i) => ! empty($i['url']))->all(),
        ])->render();
    }
}
