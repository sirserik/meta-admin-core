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
     */
    public function blockType(string $key): array;
}
