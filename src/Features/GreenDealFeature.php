<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;

class GreenDealFeature extends FeatureModule
{
    public function name(): string        { return 'green_deal'; }
    public function label(): string       { return 'Green Deal Center'; }
    public function description(): string { return 'Админка для страниц Green Deal Center с переводами ru/kk/en.'; }
    public function icon(): string        { return 'fa-leaf'; }

    public function available(): bool
    {
        return class_exists('App\\Models\\GdcPage');
    }

    public function unavailableReason(): ?string
    {
        return 'Модель App\\Models\\GdcPage не найдена. Опубликуй миграции пакета + скопируй GdcPage + GdcPageTranslation в app/Models/.';
    }

    public function register(AdminCore $core): void
    {
        $core->resource('gdc-pages', [
            'model'        => 'App\\Models\\GdcPage',
            'label'        => 'Страницы GDC',
            'menu'         => 'Green Deal Center',
            'icon'         => 'fa-leaf',
            'order_by'     => ['order' => 'asc', 'id' => 'asc'],
            'translatable' => ['title', 'subtitle', 'content'],
            'fields' => [
                ['name' => 'title',    'type' => 'text',     'label' => 'Заголовок',    'required' => true, 'group' => 'Основная информация', 'group_icon' => 'fa-info-circle'],
                ['name' => 'subtitle', 'type' => 'textarea', 'label' => 'Подзаголовок', 'group' => 'Основная информация'],
                ['name' => 'content',  'type' => 'editor',   'label' => 'Содержимое',   'group' => 'Основная информация'],
            ],
            'attributes' => [
                ['name' => 'slug',      'type' => 'text',    'label' => 'Slug',    'required' => true, 'group' => 'Параметры', 'group_icon' => 'fa-sliders'],
                ['name' => 'order',     'type' => 'number',  'label' => 'Порядок', 'group' => 'Параметры'],
                ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Активна', 'group' => 'Параметры'],
            ],
            'dim' => fn ($p) => !$p->is_active,
        ]);

        // Direct link to GDC public landing page blocks.
        $prefix = config('admin-core.prefix', 'admin');
        $core->menuItem('Страница центра', "/{$prefix}/blocks?page=green-deal-center", 'fa-globe', 'Green Deal Center', 20);
    }
}
