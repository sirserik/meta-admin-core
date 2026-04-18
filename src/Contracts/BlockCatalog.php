<?php

namespace Meta\AdminCore\Contracts;

/**
 * Descriptor that tells the admin page-builder UI which page slugs
 * and block types it should show in dropdowns, with labels/icons.
 *
 * Consumers bind their own implementation in a service provider to
 * describe their site's content architecture:
 *
 *   $this->app->bind(
 *       \Meta\AdminCore\Contracts\BlockCatalog::class,
 *       \App\Support\BlockCatalog::class,
 *   );
 *
 * The package ships a minimal default
 * (Meta\AdminCore\Support\DefaultBlockCatalog) for sites that don't
 * have a fancy catalog yet.
 */
interface BlockCatalog
{
    /**
     * Grouped pages: ['Основные' => ['home' => 'Главная', ...], ...].
     */
    public function pagesGrouped(): array;

    /**
     * Human label for a specific page slug.
     */
    public function pageLabel(string $slug): string;

    /**
     * Block types grouped by category:
     * ['Контент' => [['key' => 'hero', 'label' => 'Герой', 'icon' => 'fa-star'], ...], ...].
     */
    public function blockTypesGrouped(): array;

    /**
     * Flat list of block types: [['key' => ..., 'label' => ..., 'icon' => ...], ...].
     */
    public function blockTypesFlat(): array;

    /**
     * Metadata for a specific block type, or null/empty on miss.
     * Shape: ['key' => ..., 'label' => ..., 'icon' => ..., 'description' => ...].
     * May also include a `data_schema` key (see blockSchema()).
     */
    public function blockType(string $key): array;

    /**
     * Schema describing the shape of a block's `data` field.
     *
     * Used by the visual editor to render form inputs instead of raw
     * JSON. Return null when no schema exists — the UI will fall back
     * to a JSON textarea.
     *
     * Return shape:
     *   [
     *     'items' => [
     *       [
     *         'key'   => 'slides',          // JSON array key
     *         'label' => 'Слайды',
     *         'type'  => 'array',           // 'array' | 'text' | 'textarea' | 'image' | 'url'
     *         'item_fields' => [            // when type === 'array'
     *           ['key' => 'title',     'label' => 'Заголовок', 'type' => 'text'],
     *           ['key' => 'image',     'label' => 'Картинка',  'type' => 'image'],
     *           ['key' => 'cta_url',   'label' => 'Ссылка',    'type' => 'url'],
     *         ],
     *       ],
     *     ],
     *   ]
     */
    public function blockSchema(string $key): ?array;
}
