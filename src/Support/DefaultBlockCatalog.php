<?php

namespace Meta\AdminCore\Support;

use Meta\AdminCore\Contracts\BlockCatalog;

/**
 * Minimal catalog for consumer sites that don't ship their own yet.
 * Lists a handful of typical pages + generic block types so the admin
 * page-builder renders something sensible out of the box.
 */
class DefaultBlockCatalog implements BlockCatalog
{
    public function pagesGrouped(): array
    {
        return [
            'Основные' => [
                'home'    => 'Главная страница',
                'about'   => 'О нас',
                'contact' => 'Контакты',
            ],
            'Служебные' => [
                'header'   => 'Шапка сайта',
                'footer'   => 'Подвал',
                'settings' => 'Глобальные настройки',
            ],
        ];
    }

    public function pageLabel(string $slug): string
    {
        foreach ($this->pagesGrouped() as $pages) {
            if (isset($pages[$slug])) return $pages[$slug];
        }
        return $slug;
    }

    public function blockTypesGrouped(): array
    {
        return [
            'Контент' => [
                ['key' => 'content', 'label' => 'Текстовый блок', 'icon' => 'fa-align-left', 'description' => 'Произвольный текст.'],
                ['key' => 'hero',    'label' => 'Герой',          'icon' => 'fa-star',       'description' => 'Крупный заголовок, подзаголовок, кнопка.'],
                ['key' => 'gallery', 'label' => 'Галерея',        'icon' => 'fa-images',     'description' => 'Серия изображений.'],
            ],
            'Служебные' => [
                ['key' => 'settings',     'label' => 'Настройки',     'icon' => 'fa-cog',     'description' => 'Произвольные key-value данные.'],
                ['key' => 'menu_settings','label' => 'Настройки меню','icon' => 'fa-bars',    'description' => 'Включить/выключить/переупорядочить пункты меню.'],
                ['key' => 'social-links', 'label' => 'Соц-сети',      'icon' => 'fa-share',   'description' => 'Ссылки на социальные сети.'],
                ['key' => 'image',        'label' => 'Картинка',      'icon' => 'fa-image',   'description' => 'Одна картинка с опциональной ссылкой.'],
            ],
        ];
    }

    public function blockTypesFlat(): array
    {
        $flat = [];
        foreach ($this->blockTypesGrouped() as $category => $types) {
            foreach ($types as $info) {
                $flat[] = array_merge($info, ['category' => $category]);
            }
        }
        return $flat;
    }

    public function blockType(string $key): array
    {
        foreach ($this->blockTypesFlat() as $info) {
            if ($info['key'] === $key) return $info;
        }
        return [
            'key'         => $key,
            'label'       => $key,
            'icon'        => 'fa-puzzle-piece',
            'description' => '',
            'category'    => 'Прочее',
        ];
    }
}
