<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;

class StatsBlock extends BlockDefinition
{
    public function variants(): array
    {
        return [
            'default' => 'Базовый (сетка с цветным фоном)',
            'inline'  => 'Инлайн (одна строка, без фона)',
        ];
    }

    public function handle(): string
    {
        return 'stats';
    }

    public function label(): string
    {
        return 'Stats — счётчики';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'chart-bar';
    }

    public function schema(): array
    {
        return [
            'variant' => [
                'type' => 'select',
                'label' => 'Вариант вёрстки',
                'default' => 'default',
                'options' => $this->variants(),
            ],
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'background' => [
                'type' => 'select',
                'label' => 'Фон',
                'default' => 'red',
                'options' => ['red' => 'Red gradient', 'gold' => 'Gold', 'dark' => 'Dark', 'white' => 'Белый', 'gray' => 'Серый'],
            ],
            'columns' => [
                'type' => 'select',
                'label' => 'Колонок',
                'default' => '4',
                'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'],
            ],
            'stats' => [
                'type' => 'repeater',
                'label' => 'Счётчики',
                'fields' => [
                    'value' => ['type' => 'text', 'label' => 'Число', 'required' => true, 'help' => 'Например "3+" или "98%"'],
                    'label' => ['type' => 'text', 'label' => 'Подпись', 'translatable' => true, 'required' => true],
                    'icon' => ['type' => 'text', 'label' => 'Иконка (FA)', 'default' => 'fas fa-chart-line'],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return $this->renderVariant($data, $locale, [
            'title'      => $this->localized($data['title']    ?? ($data['_title']    ?? null), $locale),
            'subtitle'   => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'background' => $data['background'] ?? ($data['_settings']['background'] ?? 'red'),
            'columns'    => (int) ($data['columns'] ?? 4),
            'stats' => collect($data['stats'] ?? [])->map(fn ($s) => [
                'value' => $s['value'] ?? $s['number'] ?? '',
                'label' => $this->localized($s['label'] ?? null, $locale),
                'icon'  => $s['icon'] ?? null,
            ])->filter(fn ($s) => ! empty($s['value']))->all(),
        ]);
    }
}
