<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class ProgramsBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'programs';
    }

    public function label(): string
    {
        return 'Programs — список программ обучения';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'graduation-cap';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'columns' => ['type' => 'select', 'label' => 'Колонок', 'default' => '3', 'options' => ['2' => '2', '3' => '3', '4' => '4']],
            'programs' => [
                'type' => 'repeater',
                'label' => 'Программы',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Название', 'translatable' => true, 'required' => true],
                    'description' => ['type' => 'textarea', 'label' => 'Описание', 'translatable' => true],
                    'icon' => ['type' => 'text', 'label' => 'Иконка (FA)', 'default' => 'fas fa-graduation-cap'],
                    'url' => ['type' => 'url', 'label' => 'Ссылка'],
                    'duration' => ['type' => 'text', 'label' => 'Длительность', 'translatable' => true],
                    'level' => ['type' => 'text', 'label' => 'Уровень', 'translatable' => true],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.programs', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'columns' => (int) ($data['columns'] ?? 3),
            'programs' => collect($data['programs'] ?? [])->map(fn ($p) => [
                'title' => $this->localized($p['title'] ?? null, $locale),
                'description' => $this->localized($p['description'] ?? null, $locale),
                'icon' => $p['icon'] ?? 'fas fa-graduation-cap',
                'url' => $this->localized($p['url'] ?? null, $locale) ?: '#',
                'duration' => $this->localized($p['duration'] ?? null, $locale),
                'level' => $this->localized($p['level'] ?? null, $locale),
            ])->filter(fn ($p) => ! empty($p['title']))->all(),
        ])->render();
    }
}
