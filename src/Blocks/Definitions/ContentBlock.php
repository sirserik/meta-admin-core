<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class ContentBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'content';
    }

    public function label(): string
    {
        return 'Content — текстовый блок';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'align-left';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'content' => ['type' => 'richtext', 'label' => 'Основной текст', 'translatable' => true],
            'background' => [
                'type' => 'select',
                'label' => 'Фон',
                'default' => 'light',
                'options' => [
                    'light' => 'Светлый (gradient)',
                    'white' => 'Белый',
                    'gray' => 'Серый',
                    'dark' => 'Тёмный',
                ],
            ],
            'icon' => [
                'type' => 'text',
                'label' => 'Иконка (FontAwesome класс)',
                'default' => 'fas fa-info-circle',
                'help' => 'Например: fas fa-graduation-cap, fas fa-users',
            ],
            'gradient_from' => ['type' => 'color', 'label' => 'Градиент (цвет 1)', 'default' => '#dc2626'],
            'gradient_to' => ['type' => 'color', 'label' => 'Градиент (цвет 2)', 'default' => '#b91c1c'],
        ];
    }

    public function render(array $data, string $locale): string
    {
        // Legacy-поля из row (title/subtitle/content) могут прийти как _title/_subtitle/_content
        $title = $data['title'] ?? $data['_title'] ?? null;
        $subtitle = $data['subtitle'] ?? $data['_subtitle'] ?? null;
        $content = $data['content'] ?? $data['_content'] ?? null;

        return View::make('blocks.v2.content', [
            'title' => $this->localized($title, $locale),
            'subtitle' => $this->localized($subtitle, $locale),
            'content' => $this->localized($content, $locale),
            'background' => $data['background'] ?? ($data['_settings']['background_color'] ?? 'light'),
            'icon' => $data['icon'] ?? ($data['_settings']['icon'] ?? 'fas fa-info-circle'),
            'gradientFrom' => $data['gradient_from'] ?? ($data['_settings']['gradient_from'] ?? '#dc2626'),
            'gradientTo' => $data['gradient_to'] ?? ($data['_settings']['gradient_to'] ?? '#b91c1c'),
        ])->render();
    }
}
