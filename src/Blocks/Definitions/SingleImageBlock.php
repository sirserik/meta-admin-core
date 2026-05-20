<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class SingleImageBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'single_image';
    }

    public function label(): string
    {
        return 'Single Image — крупное изображение/скан';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'image';
    }

    public function schema(): array
    {
        return [
            'image' => ['type' => 'media', 'label' => 'Изображение', 'required' => true],
            'caption' => ['type' => 'text', 'label' => 'Подпись', 'translatable' => true],
            'alt' => ['type' => 'text', 'label' => 'Alt-текст', 'translatable' => true],
            'width' => [
                'type' => 'select',
                'label' => 'Ширина',
                'default' => 'container',
                'options' => ['container' => 'По контейнеру', 'wide' => 'Широкая', 'full' => 'На всю ширину окна'],
            ],
            'aspect' => [
                'type' => 'select',
                'label' => 'Пропорции',
                'default' => 'auto',
                'options' => ['auto' => 'Авто', 'square' => 'Квадрат', '4-3' => '4:3', '16-9' => '16:9', 'a4' => 'A4 (для сканов)'],
            ],
            'enable_zoom' => ['type' => 'checkbox', 'label' => 'Открывать на полный экран по клику', 'default' => true],
            'background' => [
                'type' => 'select',
                'label' => 'Фон',
                'default' => 'none',
                'options' => ['none' => 'Нет', 'gray' => 'Серый', 'soft' => 'Мягкий'],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.single-image', [
            'image' => $data['image'] ?? '',
            'caption' => $this->localized($data['caption'] ?? null, $locale),
            'alt' => $this->localized($data['alt'] ?? null, $locale) ?: '',
            'width' => $data['width'] ?? 'container',
            'aspect' => $data['aspect'] ?? 'auto',
            'enableZoom' => (bool) ($data['enable_zoom'] ?? true),
            'background' => $data['background'] ?? 'none',
        ])->render();
    }
}
