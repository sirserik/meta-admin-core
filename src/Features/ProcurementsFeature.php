<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;
use Meta\AdminCore\Models\Procurement;

/**
 * Procurements / tenders directory. Each row carries procedure
 * metadata (number/type/status/dates/budget/customer) plus a
 * polymorphic block stack via `HasContentBlocks` — operators
 * compose page content (description tables, document groups,
 * scans, videos, anything) per procurement, no per-page code.
 *
 * Self-registers:
 *  - `procurements` resource on `/admin/procurements` — generic
 *    Resource/{Index,Form}.vue with translatable ru/kk/en tabs,
 *    status / procurement_type filters; `edit_url` jumps straight
 *    to the block builder for that row.
 *  - Preview-URL resolver mapping synthetic `procurement-{id}`
 *    page_names to `/procurements/{slug}` so the live-preview
 *    iframe loads the real public page.
 *
 * Consumer site is responsible for the public side: routes
 * (/procurements + /procurements/{slug}), the listing & detail
 * Blade views, frontend translations, and any sites-specific
 * extras (menu link, observer to back-fill `blockable_*` from
 * the synthetic page_name, etc.). The package provides a working
 * model + admin form so operators can edit content immediately;
 * each consumer adds the public layer that fits its design.
 */
class ProcurementsFeature extends FeatureModule
{
    public function name(): string        { return 'procurements'; }
    public function label(): string       { return 'Закупки'; }
    public function description(): string { return 'Реестр закупок: номер, тип процедуры, статус, дедлайны, бюджет; для каждой строки — гибкий стек блоков контента (таблицы документов, скан-галереи, видео).'; }
    public function icon(): string        { return 'fa-file-invoice-dollar'; }

    public function register(AdminCore $core): void
    {
        $types    = collect(Procurement::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();
        $statuses = collect(Procurement::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();

        $core->resource('procurements', [
            'model'         => Procurement::class,
            'label'         => 'Закупки',
            'menu'          => 'Контент',
            'icon'          => 'fa-file-invoice-dollar',
            'order_by'      => ['announced_at' => 'desc', 'id' => 'desc'],
            'translatable'  => ['title', 'summary', 'customer'],
            'filters' => [
                'status' => [
                    'type'     => 'exact', 'column' => 'status', 'label' => 'Статус',
                    'resolver' => fn ($v) => Procurement::STATUSES[$v] ?? $v,
                ],
                'procurement_type' => [
                    'type'     => 'exact', 'column' => 'procurement_type', 'label' => 'Тип закупки',
                    'resolver' => fn ($v) => Procurement::TYPES[$v] ?? $v,
                ],
            ],
            'fields' => [
                ['name' => 'title',    'type' => 'text',     'label' => 'Название',         'required' => true],
                ['name' => 'summary',  'type' => 'textarea', 'label' => 'Краткое описание'],
                ['name' => 'customer', 'type' => 'text',     'label' => 'Заказчик'],
            ],
            'attributes' => [
                ['name' => 'number',           'type' => 'text',           'label' => '№ процедуры', 'placeholder' => '2026-001', 'max' => 100],
                ['name' => 'procurement_type', 'type' => 'select',         'label' => 'Тип закупки', 'options' => $types, 'required' => true],
                ['name' => 'status',           'type' => 'select',         'label' => 'Статус',      'options' => $statuses],
                ['name' => 'announced_at',     'type' => 'datetime-local', 'label' => 'Объявлена'],
                ['name' => 'deadline_at',      'type' => 'datetime-local', 'label' => 'Дедлайн'],
                ['name' => 'completed_at',     'type' => 'datetime-local', 'label' => 'Завершена'],
                ['name' => 'budget',           'type' => 'number',         'label' => 'Бюджет'],
                ['name' => 'budget_currency',  'type' => 'text',           'label' => 'Валюта', 'placeholder' => 'KZT', 'max' => 8],
                ['name' => 'budget_public',    'type' => 'boolean',        'label' => 'Показывать бюджет публично'],
                ['name' => 'is_published',     'type' => 'boolean',        'label' => 'Опубликовано'],
                ['name' => 'sort_order',       'type' => 'number',         'label' => 'Порядок'],
            ],
            // After saving meta-fields, jump to the per-procurement block
            // builder. Synthetic page_name `procurement-{id}` is the
            // canonical key the polymorphic blocks use.
            'edit_url' => fn ($item) => '/admin/blocks?page=procurement-' . $item->id,
        ]);

        // Live-preview iframe URL for blocks attached to a procurement row.
        $core->previewResolver('/^procurement-(\d+)$/', function (array $matches) {
            $p = Procurement::find((int) $matches[1]);
            return $p ? '/procurements/' . $p->slug : null;
        });
    }
}
