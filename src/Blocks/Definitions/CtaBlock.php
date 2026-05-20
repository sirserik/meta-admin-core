<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class CtaBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'CTA — call-to-action';
    }

    public function category(): string
    {
        return 'cta';
    }

    public function icon(): string
    {
        return 'bullseye';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'textarea', 'label' => 'Подзаголовок', 'translatable' => true],
            'badge' => ['type' => 'text', 'label' => 'Бейдж', 'translatable' => true],
            'background' => [
                'type' => 'select',
                'label' => 'Фон',
                'default' => 'red',
                'options' => [
                    'red' => 'Red (gradient)',
                    'gold' => 'Gold (gradient)',
                    'blue' => 'Blue (gradient)',
                    'dark' => 'Dark (gradient)',
                    'gray' => 'Серый',
                    'white' => 'Белый',
                ],
            ],
            'style' => [
                'type' => 'select',
                'label' => 'Раскладка',
                'default' => 'centered',
                'options' => ['centered' => 'По центру', 'split' => 'Split (2 колонки)', 'minimal' => 'Минимальный'],
            ],
            'buttons' => [
                'type' => 'repeater',
                'label' => 'Кнопки',
                'max' => 3,
                'fields' => [
                    'text' => ['type' => 'text', 'translatable' => true, 'required' => true],
                    'url' => ['type' => 'url', 'required' => true],
                    'style' => ['type' => 'select', 'default' => 'primary', 'options' => ['primary' => 'Primary', 'outline' => 'Outline']],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.cta', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'badge' => $this->localized($data['badge'] ?? null, $locale),
            'background' => $data['background'] ?? ($data['_settings']['background_type'] ?? 'red'),
            'style' => $data['style'] ?? ($data['_settings']['style'] ?? 'centered'),
            'buttons' => collect($data['buttons'] ?? [])->map(fn ($b) => [
                'text' => $this->localized($b['text'] ?? null, $locale),
                'url' => $this->localized($b['url'] ?? null, $locale) ?: '#',
                'style' => $b['style'] ?? 'primary',
            ])->filter(fn ($b) => ! empty($b['text']))->all(),
        ])->render();
    }
}
