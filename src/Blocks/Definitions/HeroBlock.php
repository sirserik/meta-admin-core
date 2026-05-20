<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;

class HeroBlock extends BlockDefinition
{
    public function variants(): array
    {
        return [
            'default'  => 'Базовый (центр, фон-градиент)',
            'centered' => 'По центру, светлый',
            'split'    => 'Сплит с картинкой справа',
        ];
    }

    public function handle(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero — главный баннер';
    }

    public function category(): string
    {
        return 'layout';
    }

    public function icon(): string
    {
        return 'star';
    }

    public function schema(): array
    {
        return [
            'variant' => [
                'type' => 'select',
                'label' => 'Вариант вёрстки',
                'default' => 'default',
                'options' => $this->variants(),
                'help' => 'Подбирает один из готовых шаблонов рендера для этого блока.',
            ],
            'badge' => [
                'type' => 'text',
                'label' => 'Бейдж над заголовком',
                'translatable' => true,
                'help' => 'Короткий текст-метка, например "Приемная кампания 2026"',
            ],
            'title' => [
                'type' => 'text',
                'label' => 'Заголовок',
                'translatable' => true,
            ],
            'subtitle' => [
                'type' => 'textarea',
                'label' => 'Подзаголовок',
                'translatable' => true,
            ],
            'background' => [
                'type' => 'select',
                'label' => 'Фон',
                'default' => 'gradient',
                'options' => [
                    'gradient' => 'Градиент',
                    'solid' => 'Сплошной цвет',
                    'image' => 'Изображение',
                ],
            ],
            'background_image' => [
                'type' => 'media',
                'label' => 'Изображение фона',
                'help' => 'Используется если фон = image',
            ],
            'buttons' => [
                'type' => 'repeater',
                'label' => 'Кнопки',
                'max' => 3,
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Текст', 'translatable' => true, 'required' => true],
                    'url' => ['type' => 'url', 'label' => 'Ссылка', 'required' => true],
                    'style' => [
                        'type' => 'select',
                        'label' => 'Стиль',
                        'default' => 'primary',
                        'options' => ['primary' => 'Primary', 'outline' => 'Outline'],
                    ],
                ],
            ],
            'stats' => [
                'type' => 'repeater',
                'label' => 'Счётчики (статистика)',
                'max' => 6,
                'fields' => [
                    'number' => ['type' => 'text', 'label' => 'Число', 'required' => true],
                    'label' => ['type' => 'text', 'label' => 'Подпись', 'translatable' => true, 'required' => true],
                ],
            ],
            'slides' => [
                'type' => 'repeater',
                'label' => 'Слайды (для слайдшоу)',
                'fields' => [
                    'image' => ['type' => 'media', 'label' => 'Изображение', 'required' => true],
                    'alt' => ['type' => 'text', 'label' => 'Alt-текст', 'translatable' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        // Pre-flatten translatable values so variant templates don't repeat
        // the ->localized() dance; pass through renderVariant which picks
        // the right blade based on data['variant'].
        return $this->renderVariant($data, $locale, [
            'badge'           => $this->localized($data['badge'] ?? null, $locale),
            'title'           => $this->localized($data['title'] ?? null, $locale),
            'subtitle'        => $this->localized($data['subtitle'] ?? null, $locale),
            'background'      => $data['background'] ?? 'gradient',
            'backgroundImage' => $data['background_image'] ?? null,
            'buttons' => collect($data['buttons'] ?? [])->map(fn ($b) => [
                'text'  => $this->localized($b['text'] ?? null, $locale),
                'url'   => $this->localized($b['url'] ?? null, $locale) ?: '#',
                'style' => $b['style'] ?? 'primary',
            ])->all(),
            'stats' => collect($data['stats'] ?? [])->map(fn ($s) => [
                'number' => $s['number'] ?? '',
                'label'  => $this->localized($s['label'] ?? null, $locale),
            ])->all(),
            'slides' => $data['slides'] ?? [],
        ]);
    }
}
