<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;

/**
 * SDG (Sustainable Development Goals) portal — admin UI for the
 * university's 17-goal sustainability portal. Registers two
 * resources:
 *  - sdg-goals  — the 17 UN goals (number, color, icon, content)
 *  - sdg-news   — news items linked to a goal
 *
 * Page-level content (sdg-portal landing, sdg-portal-news) lives in
 * page_blocks — edited via /admin/blocks.
 *
 * Consumer sites need `App\Models\SdgGoal` and `App\Models\SdgNews`
 * for this feature to activate. If either is missing the feature
 * shows as unavailable in /admin/features with a hint.
 */
class SdgFeature extends FeatureModule
{
    public function name(): string        { return 'sdg'; }
    public function label(): string       { return 'ЦУР (SDG) Портал'; }
    public function description(): string { return 'Портал целей устойчивого развития: 17 целей + новости, связанные с ними.'; }
    public function icon(): string        { return 'fa-earth-americas'; }

    public function available(): bool
    {
        return class_exists('App\\Models\\SdgGoal')
            && class_exists('App\\Models\\SdgNews');
    }

    public function unavailableReason(): ?string
    {
        return 'Нужны модели App\\Models\\SdgGoal и App\\Models\\SdgNews. Скопируй их и миграции sdg_goals / sdg_news таблиц из референсного сайта.';
    }

    public function register(AdminCore $core): void
    {
        $sdgGoalOptions = function () {
            if (!class_exists('App\\Models\\SdgGoal')) return [];
            return \App\Models\SdgGoal::query()->orderBy('number')->get(['id', 'number'])
                ->map(fn ($g) => [
                    'value' => $g->id,
                    'label' => 'Цель ' . $g->number . ' — ' . ($g->translate('title', 'ru') ?? ''),
                ])->all();
        };

        $core->resource('sdg-goals', [
            'model'        => 'App\\Models\\SdgGoal',
            'label'        => 'Цели ЦУР',
            'menu'         => 'ЦУР (SDG)',
            'icon'         => 'fa-bullseye',
            'image_field'  => 'image',
            'order_by'     => ['sort_order' => 'asc', 'number' => 'asc'],
            'translatable' => ['title', 'short_description', 'content'],
            'fields' => [
                ['name' => 'title',             'type' => 'text',     'label' => 'Название', 'required' => true, 'group' => 'Основная информация', 'group_icon' => 'fa-info-circle'],
                ['name' => 'short_description', 'type' => 'textarea', 'label' => 'Краткое описание', 'group' => 'Основная информация'],
                ['name' => 'content',           'type' => 'editor',   'label' => 'Содержание', 'group' => 'Основная информация'],
            ],
            'attributes' => [
                ['name' => 'number',     'type' => 'number',  'label' => 'Номер (1–17)', 'required' => true, 'group' => 'Параметры', 'group_icon' => 'fa-sliders'],
                ['name' => 'color',      'type' => 'color',   'label' => 'Цвет',         'placeholder' => '#C41E3A', 'group' => 'Параметры'],
                ['name' => 'icon',       'type' => 'text',    'label' => 'Иконка (FA)',  'placeholder' => 'fa-bullseye', 'group' => 'Параметры'],
                ['name' => 'sort_order', 'type' => 'number',  'label' => 'Порядок', 'group' => 'Параметры'],
                ['name' => 'is_active',  'type' => 'boolean', 'label' => 'Активна', 'group' => 'Параметры'],
            ],
            'dim' => fn ($g) => !$g->is_active,
        ]);

        // Direct link to the SDG portal landing page blocks — editing
        // content like the hero/stats/partners lives on /admin/blocks
        // filtered to the `sdg-portal` page_name.
        $prefix = config('admin-core.prefix', 'admin');
        $core->menuItem('Страница портала', "/{$prefix}/blocks?page=sdg-portal",      'fa-globe',      'ЦУР (SDG)', 30);
        $core->menuItem('Новостная лента',  "/{$prefix}/blocks?page=sdg-portal-news", 'fa-newspaper',  'ЦУР (SDG)', 31);

        $core->resource('sdg-news', [
            'model'        => 'App\\Models\\SdgNews',
            'label'        => 'Новости ЦУР',
            'menu'         => 'ЦУР (SDG)',
            'icon'         => 'fa-bullhorn',
            'image_field'  => 'image',
            'order_by'     => ['published_at' => 'desc', 'id' => 'desc'],
            'translatable' => ['title', 'excerpt', 'content'],
            'fields' => [
                ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок',       'required' => true, 'group' => 'Содержимое', 'group_icon' => 'fa-newspaper'],
                ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание', 'group' => 'Содержимое'],
                ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое',       'group' => 'Содержимое'],
            ],
            'attributes' => [
                ['name' => 'sdg_goal_id',  'type' => 'select', 'label' => 'Цель ЦУР',   'options' => $sdgGoalOptions, 'group' => 'Параметры', 'group_icon' => 'fa-sliders'],
                ['name' => 'status',       'type' => 'select', 'label' => 'Статус',     'options' => [
                    ['value' => 'draft',     'label' => 'Черновик'],
                    ['value' => 'published', 'label' => 'Опубликовано'],
                    ['value' => 'archived',  'label' => 'В архиве'],
                ], 'group' => 'Параметры'],
                ['name' => 'published_at', 'type' => 'datetime-local', 'label' => 'Дата публикации', 'group' => 'Параметры'],
            ],
        ]);
    }
}
