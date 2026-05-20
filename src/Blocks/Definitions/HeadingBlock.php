<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class HeadingBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'heading';
    }

    public function label(): string
    {
        return 'Heading — заголовок секции';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'heading';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true, 'required' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'icon' => ['type' => 'text', 'label' => 'Иконка (FA)', 'help' => 'Например: fas fa-graduation-cap'],
            'alignment' => [
                'type' => 'select',
                'label' => 'Выравнивание',
                'default' => 'center',
                'options' => ['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'],
            ],
            'size' => [
                'type' => 'select',
                'label' => 'Размер',
                'default' => 'medium',
                'options' => ['small' => 'Маленький', 'medium' => 'Средний', 'large' => 'Большой'],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.heading', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'icon' => $data['icon'] ?? null,
            'alignment' => $data['alignment'] ?? 'center',
            'size' => $data['size'] ?? 'medium',
        ])->render();
    }
}
