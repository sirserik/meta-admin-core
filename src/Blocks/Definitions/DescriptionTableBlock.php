<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class DescriptionTableBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'description_table';
    }

    public function label(): string
    {
        return 'Description Table — таблица «описание + ссылка»';
    }

    public function category(): string
    {
        return 'content';
    }

    public function icon(): string
    {
        return 'table-list';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'show_index' => ['type' => 'checkbox', 'label' => 'Показывать №', 'default' => true],
            'show_date' => ['type' => 'checkbox', 'label' => 'Показывать дату', 'default' => true],
            'striped' => ['type' => 'checkbox', 'label' => 'Зебра', 'default' => true],
            'rows' => [
                'type' => 'repeater',
                'label' => 'Строки',
                'fields' => [
                    'description' => ['type' => 'richtext', 'label' => 'Описание', 'translatable' => true, 'required' => true],
                    'date' => ['type' => 'date', 'label' => 'Дата'],
                    'link_label' => ['type' => 'text', 'label' => 'Текст ссылки', 'translatable' => true, 'default' => 'Открыть'],
                    'link_url' => ['type' => 'url', 'label' => 'URL или путь к файлу'],
                    'link_type' => [
                        'type' => 'select',
                        'label' => 'Тип ссылки',
                        'default' => 'document',
                        'options' => ['document' => 'Документ', 'external' => 'Внешняя ссылка', 'video' => 'Видео', 'image' => 'Изображение'],
                    ],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        $rows = collect($data['rows'] ?? [])
            ->map(fn ($r) => [
                'description' => $this->localized($r['description'] ?? null, $locale),
                'date' => $r['date'] ?? null,
                'link_label' => $this->localized($r['link_label'] ?? null, $locale) ?: 'Открыть',
                'link_url' => $this->localized($r['link_url'] ?? null, $locale),
                'link_type' => $r['link_type'] ?? 'document',
            ])
            ->filter(fn ($r) => ! empty($r['description']))
            ->values();

        return View::make('blocks.v2.description-table', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'rows' => $rows,
            'showIndex' => (bool) ($data['show_index'] ?? true),
            'showDate' => (bool) ($data['show_date'] ?? true),
            'striped' => (bool) ($data['striped'] ?? true),
        ])->render();
    }
}
