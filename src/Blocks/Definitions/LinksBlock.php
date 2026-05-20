<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class LinksBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'links';
    }

    public function label(): string
    {
        return 'Links — список ссылок/документов';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'link';
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
                'options' => ['grid' => 'Сетка', 'list' => 'Список'],
            ],
            'columns' => [
                'type' => 'select',
                'label' => 'Колонок (grid)',
                'default' => '3',
                'options' => ['2' => '2', '3' => '3', '4' => '4'],
            ],
            'links' => [
                'type' => 'repeater',
                'label' => 'Ссылки',
                'fields' => [
                    'icon' => ['type' => 'text', 'label' => 'Иконка (FA)', 'default' => 'fas fa-file'],
                    'title' => ['type' => 'text', 'label' => 'Название', 'translatable' => true, 'required' => true],
                    'description' => ['type' => 'text', 'label' => 'Описание', 'translatable' => true],
                    'url' => ['type' => 'url', 'label' => 'Ссылка', 'required' => true],
                    'external' => ['type' => 'checkbox', 'label' => 'Открывать в новой вкладке'],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        return View::make('blocks.v2.links', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'layout' => $data['layout'] ?? 'grid',
            'columns' => (int) ($data['columns'] ?? 3),
            'links' => collect($data['links'] ?? [])->map(fn ($l) => [
                'icon' => $l['icon'] ?? 'fas fa-link',
                'title' => $this->localized($l['title'] ?? null, $locale),
                'description' => $this->localized($l['description'] ?? null, $locale),
                'url' => $this->localized($l['url'] ?? null, $locale) ?: '#',
                'external' => (bool) ($l['external'] ?? false),
            ])->filter(fn ($l) => ! empty($l['title']))->all(),
        ])->render();
    }
}
