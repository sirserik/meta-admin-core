<?php

namespace Meta\AdminCore\Blocks\Definitions;

use Meta\AdminCore\Blocks\BlockDefinition;
use Illuminate\Support\Facades\View;

class DocumentGroupBlock extends BlockDefinition
{
    public function handle(): string
    {
        return 'document_group';
    }

    public function label(): string
    {
        return 'Document Group — документы с группировкой';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'folder-tree';
    }

    public function schema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Заголовок', 'translatable' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Подзаголовок', 'translatable' => true],
            'group_by' => [
                'type' => 'select',
                'label' => 'Группировать по',
                'default' => 'category',
                'options' => ['category' => 'Категории', 'date' => 'Дате (по году)', 'none' => 'Без группировки'],
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'Вид',
                'default' => 'tabs',
                'options' => ['tabs' => 'Вкладки', 'accordion' => 'Аккордеон', 'list' => 'Список'],
            ],
            'documents' => [
                'type' => 'repeater',
                'label' => 'Документы',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Название', 'translatable' => true, 'required' => true],
                    'description' => ['type' => 'text', 'label' => 'Описание', 'translatable' => true],
                    'category' => ['type' => 'text', 'label' => 'Категория', 'translatable' => true, 'help' => 'Используется при группировке по категории'],
                    'file' => ['type' => 'media', 'label' => 'Файл (PDF/DOC и др.)', 'required' => true],
                    'size' => ['type' => 'text', 'label' => 'Размер (например 1.2 MB)'],
                    'date' => ['type' => 'date', 'label' => 'Дата документа'],
                ],
            ],
        ];
    }

    public function render(array $data, string $locale): string
    {
        $documents = collect($data['documents'] ?? [])
            ->map(function ($d) use ($locale) {
                $file = $d['file'] ?? '';
                if (empty($file)) {
                    return null;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                return [
                    'title' => $this->localized($d['title'] ?? null, $locale) ?: 'Документ',
                    'description' => $this->localized($d['description'] ?? null, $locale),
                    'category' => $this->localized($d['category'] ?? null, $locale) ?: '—',
                    'file' => $file,
                    'ext' => $ext,
                    'size' => $d['size'] ?? null,
                    'date' => $d['date'] ?? null,
                    'year' => ! empty($d['date']) ? substr($d['date'], 0, 4) : '—',
                ];
            })
            ->filter()
            ->values();

        $groupBy = $data['group_by'] ?? 'category';
        $groups = match ($groupBy) {
            'date' => $documents->groupBy('year')->sortKeysDesc(),
            'none' => collect(['' => $documents]),
            default => $documents->groupBy('category'),
        };

        return View::make('blocks.v2.document-group', [
            'title' => $this->localized($data['title'] ?? ($data['_title'] ?? null), $locale),
            'subtitle' => $this->localized($data['subtitle'] ?? ($data['_subtitle'] ?? null), $locale),
            'layout' => $data['layout'] ?? 'tabs',
            'groupBy' => $groupBy,
            'groups' => $groups,
            'totalCount' => $documents->count(),
        ])->render();
    }
}
