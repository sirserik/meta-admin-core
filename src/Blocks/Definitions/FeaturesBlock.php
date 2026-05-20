<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class FeaturesBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return 'Features — сетка преимуществ';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'th';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'columns' => [
                'type' => 'select',
                'label' => 'Колонок',
                'default' => '3',
                'options' => ['2' => '2', '3' => '3', '4' => '4'],
            ],
            'background' => [
                'type' => 'select',
                'label' => 'Фон секции',
                'default' => 'gray',
                'options' => ['white' => 'Белый', 'gray' => 'Серый', 'gradient' => 'Градиент'],
            ],
            'gradient_from' => ['type' => 'color', 'label' => 'Цвет иконок — от', 'default' => '#dc2626'],
            'gradient_to' => ['type' => 'color', 'label' => 'Цвет иконок — до', 'default' => '#b91c1c'],
            'features' => [
                'type' => 'repeater',
                'label' => 'Список преимуществ',
                'fields' => [
                    'icon' => ['type' => 'text', 'label' => 'Иконка (FA class)', 'default' => 'fas fa-check'],
                    'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true, 'required' => true],
                    'description' => ['type' => 'textarea', 'label' => 'Описание', 'translatable' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        $itemsRaw = $data['features'] ?? $data['items'] ?? [];

        return View::make('blocks.v2.features', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'columns' => (int) ($data['columns'] ?? $data['_settings']['columns'] ?? 3),
            'background' => $data['background'] ?? ($data['_settings']['background_color'] ?? 'gray'),
            'gradientFrom' => $data['gradient_from'] ?? ($data['_settings']['gradient_from'] ?? '#dc2626'),
            'gradientTo' => $data['gradient_to'] ?? ($data['_settings']['gradient_to'] ?? '#b91c1c'),
            'features' => collect($itemsRaw)->map(fn ($f) => [
                'icon' => $f['icon'] ?? 'fas fa-check',
                'title' => $this->localized($f['title'] ?? null, $locale),
                'description' => $this->localized($f['description'] ?? null, $locale),
            ])->filter(fn ($f) => ! empty($f['title']))->all(),
        ])->render();
    }
}
