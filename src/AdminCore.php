<?php

namespace Meta\AdminCore;

use Illuminate\Support\Collection;

/**
 * Central registry for admin resources and navigation.
 *
 * Consumer apps register resources in their AppServiceProvider:
 *
 *   AdminCore::resource('articles', [
 *       'model'             => \App\Models\Article::class,
 *       'label'             => 'Статьи',
 *       'menu'              => 'Контент',
 *       'icon'              => 'fa-newspaper',
 *       'translatable'      => ['title', 'excerpt', 'content'],
 *       'plain'             => ['slug', 'category', 'is_published', 'is_featured', 'published_at'],
 *       'image_field'       => 'featured_image',
 *       'fields'            => [
 *           ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
 *           ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
 *           ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
 *       ],
 *       'page'              => 'Articles',  // Vue component folder under pages/
 *   ]);
 *
 * Routes, nav items, and the generic CRUD controller are wired automatically.
 */
class AdminCore
{
    /** @var array<string, array<string, mixed>> */
    protected array $resources = [];

    /** @var array<string, array{label:string,href:string,icon?:string,menu?:string,order?:int}> */
    protected array $menuItems = [];

    /** @var array<int, array<string, mixed>> */
    protected array $dashboardStats = [];

    /** @var array<int, array<string, mixed>> — "recent items" widgets on dashboard */
    protected array $dashboardRecent = [];

    /** @var array<int, array<string, mixed>> — "quick action" buttons on dashboard */
    protected array $dashboardQuickActions = [];

    public function resource(string $name, array $config): self
    {
        $config['name']          = $name;
        $config['route']         = $config['route']         ?? $name;
        $config['page']          = $config['page']          ?? 'Resource'; // default to generic Resource/{Index,Form}.vue
        $config['menu']          = $config['menu']          ?? 'Контент';
        $config['icon']          = $config['icon']          ?? 'fa-file';
        $config['label']         = $config['label']         ?? ucfirst($name);
        $config['translatable']  = $config['translatable']  ?? [];
        $config['plain']         = $config['plain']         ?? [];
        $config['fields']        = $config['fields']        ?? [];
        // attributes — плоские (non-translatable) поля формы, рендерятся в
        // сайдбаре через SimpleField: text/url/email/number/date/select/boolean/color
        $config['attributes']    = $config['attributes']    ?? [];
        // actions — extra buttons/banners on the edit form. Each entry:
        //   ['label' => '…', 'icon' => 'fa-…', 'url' => fn ($item) => '...',
        //    'description' => '…' (optional), 'primary' => bool (optional)]
        // `primary` => true renders as a prominent banner at the top of the
        // form (useful for "edit related resource" CTAs). Otherwise renders
        // as a small button next to "Back to list" in the header.
        $config['actions']       = $config['actions']       ?? [];
        // filters — declare which ?key=value query params are allowed on
        // the index list. Each value: string (exact match) or
        // ['column' => 'db_col', 'type' => 'exact'|'like'|'in']. Example:
        //   'filters' => [
        //       'menu_item' => 'exact',
        //       'category'  => ['type' => 'like'],
        //   ]
        // Makes /admin/articles?menu_item=international filter by column.
        $config['filters']       = $config['filters']       ?? [];
        // edit_url — closure that returns a custom URL for "edit" links in
        // the index table. When set, row clicks skip the generic
        // /admin/{resource}/{id}/edit screen and go where you say. Useful
        // when a resource is really a façade over another (e.g. Pages →
        // PageBlocks). Receives Eloquent model, returns string URL.
        $config['edit_url']      = $config['edit_url']      ?? null;
        $config['image_field']   = $config['image_field']   ?? null;
        $config['route_key']     = $config['route_key']     ?? null; // null = use model default
        $config['per_page']      = $config['per_page']      ?? 15;
        $config['order_by']      = $config['order_by']      ?? ['created_at' => 'desc'];

        $this->resources[$name] = $config;
        return $this;
    }

    public function menuItem(string $label, string $href, string $icon = 'fa-circle', string $menu = 'Другое', int $order = 100): self
    {
        $this->menuItems[] = compact('label', 'href', 'icon', 'menu', 'order');
        return $this;
    }

    /**
     * Dashboard KPI card provider.
     * Callback returns ['label', 'value', 'icon', 'url' (optional, makes card clickable), 'trend' (optional subtitle)].
     */
    public function dashboardStat(callable $provider): self
    {
        $this->dashboardStats[] = $provider;
        return $this;
    }

    /**
     * "Recent items" dashboard widget — renders last N rows from a
     * registered resource as a small table with quick-edit links.
     *
     *   AdminCore::dashboardRecent('news', ['label' => 'Последние новости', 'limit' => 5]);
     */
    public function dashboardRecent(string $resource, array $opts = []): self
    {
        $this->dashboardRecent[] = array_merge([
            'resource' => $resource,
            'label'    => null,
            'limit'    => 5,
            'icon'     => null,
        ], $opts);
        return $this;
    }

    /**
     * Quick action button on dashboard (e.g. "New article").
     *
     *   AdminCore::dashboardQuickAction(['label' => 'Новая новость', 'url' => '/admin/news/create', 'icon' => 'fa-plus']);
     */
    public function dashboardQuickAction(array $action): self
    {
        $this->dashboardQuickActions[] = array_merge([
            'label' => '',
            'url'   => '#',
            'icon'  => 'fa-plus',
        ], $action);
        return $this;
    }

    public function getDashboardRecent(): array
    {
        return $this->dashboardRecent;
    }

    public function getDashboardQuickActions(): array
    {
        return $this->dashboardQuickActions;
    }

    public function getResources(): Collection
    {
        return collect($this->resources);
    }

    public function getResource(string $name): ?array
    {
        return $this->resources[$name] ?? null;
    }

    public function hasResource(string $name): bool
    {
        return isset($this->resources[$name]);
    }

    /**
     * Build sidebar nav grouped by 'menu' attribute, combining resources + ad-hoc menu items.
     *
     * @return array<array{section:string, items:array<array{label:string, href:string, icon:string}>}>
     */
    public function navigation(): array
    {
        $items = [];
        foreach ($this->resources as $name => $r) {
            $items[] = [
                'label' => $r['label'],
                'href'  => '/admin/' . $r['route'],
                'icon'  => 'fas ' . $r['icon'],
                'menu'  => $r['menu'],
                'order' => $r['order'] ?? 50,
            ];
        }
        foreach ($this->menuItems as $m) {
            $items[] = [
                'label' => $m['label'],
                'href'  => $m['href'],
                'icon'  => str_starts_with($m['icon'], 'fas ') ? $m['icon'] : 'fas ' . $m['icon'],
                'menu'  => $m['menu'],
                'order' => $m['order'],
            ];
        }

        usort($items, fn ($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

        $grouped = [];
        foreach ($items as $i) {
            $grouped[$i['menu']] ??= [];
            $grouped[$i['menu']][] = ['label' => $i['label'], 'href' => $i['href'], 'icon' => $i['icon']];
        }

        return array_map(
            fn ($section, $items) => ['section' => $section, 'items' => $items],
            array_keys($grouped),
            array_values($grouped),
        );
    }

    public function dashboardStats(): array
    {
        $out = [];
        foreach ($this->dashboardStats as $provider) {
            $result = $provider();
            if (is_array($result)) $out[] = $result;
        }
        return $out;
    }
}
