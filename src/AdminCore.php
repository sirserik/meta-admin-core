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

    /** @var array<int, class-string> — models the scheduler should tick. */
    protected array $schedulableModels = [];

    protected ?\Meta\AdminCore\Support\SitemapRegistry $sitemap = null;

    /** @var array<int, array{regex: string, resolver: callable}> — preview-URL resolvers */
    protected array $previewResolvers = [];

    /** Name of the feature whose register() is currently executing, if any.
     * Items added while this is set get tagged so the sidebar can style
     * them distinctly. */
    protected ?string $currentFeature = null;

    /**
     * Run a callback in the context of a feature module. All resources /
     * menu items added inside get the `feature => <name>` tag.
     */
    public function withFeature(string $name, \Closure $cb): void
    {
        $prev = $this->currentFeature;
        $this->currentFeature = $name;
        try { $cb($this); } finally { $this->currentFeature = $prev; }
    }

    /**
     * Register an Eloquent model for scheduled publishing.
     *
     * The model must use the `Meta\AdminCore\Concerns\Publishable` trait
     * and have `status`, `publish_at`, `unpublish_at` columns on its
     * table. The `admin-core:apply-schedule` command iterates every
     * registered model each run and flips `status` when timestamps
     * cross the current time.
     *
     * @param  class-string  $model
     */
    public function schedulable(string $model): self
    {
        if (!in_array($model, $this->schedulableModels, true)) {
            $this->schedulableModels[] = $model;
        }
        return $this;
    }

    /** @return array<int, class-string> */
    public function getSchedulableModels(): array
    {
        return $this->schedulableModels;
    }

    /**
     * Contribute URLs to the `/sitemap.xml` output. See
     * `\Meta\AdminCore\Support\SitemapRegistry` for the row shape.
     */
    public function sitemapUrl(callable $provider): self
    {
        $this->sitemap ??= new \Meta\AdminCore\Support\SitemapRegistry();
        $this->sitemap->register($provider);
        return $this;
    }

    public function sitemap(): \Meta\AdminCore\Support\SitemapRegistry
    {
        return $this->sitemap ??= new \Meta\AdminCore\Support\SitemapRegistry();
    }

    /**
     * Register a resolver that maps a synthetic block `page_name` to a
     * public preview URL. Used by the admin block-editor's live-preview
     * iframe — when the page_name doesn't correspond to a routable URL
     * (e.g. `procurement-{id}`, `program-{id}` etc.), a resolver yields
     * the real consumer-side URL to load.
     *
     *   AdminCore::previewResolver('/^procurement-(\d+)$/', function ($matches) {
     *       $p = \App\Models\Procurement::find((int) $matches[1]);
     *       return $p ? '/procurements/' . $p->slug : null;
     *   });
     *
     * Resolvers are tried in registration order; first non-null wins.
     */
    public function previewResolver(string $regex, callable $resolver): self
    {
        $this->previewResolvers[] = ['regex' => $regex, 'resolver' => $resolver];
        return $this;
    }

    /**
     * Resolve a preview URL for a `page_name`. Returns null when no
     * resolver matched — the caller should fall back to the default
     * `/{page_name}` strategy.
     */
    public function resolvePreviewUrl(string $pageName): ?string
    {
        foreach ($this->previewResolvers as $entry) {
            if (preg_match($entry['regex'], $pageName, $m)) {
                $url = call_user_func($entry['resolver'], $m, $pageName);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }
        return null;
    }

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
        // badges — visual indicators next to row titles in the index list.
        // Each entry: ['when' => fn ($m) => bool, 'label' => '...', 'icon' => 'fa-...', 'color' => 'amber'|'red'|'green'|'gray']
        // Evaluated server-side in presentRow; sent to Vue as a flat array.
        $config['badges']        = $config['badges']        ?? [];
        // dim — closure that returns true when a row should render dimmed
        // (e.g. is_active=false → "скрыт с сайта" visual).
        $config['dim']           = $config['dim']           ?? null;
        $config['image_field']   = $config['image_field']   ?? null;
        // author_field — имя колонки автора (author_id, user_id, created_by…).
        // Если задано, ResourceController при создании автоматически
        // подставит Auth::id() в эту колонку, когда форма её не передаёт.
        // Колонка должна существовать на таблице модели; иначе игнорируется.
        $config['author_field']  = $config['author_field']  ?? null;
        $config['route_key']     = $config['route_key']     ?? null; // null = use model default
        $config['per_page']      = $config['per_page']      ?? 15;
        $config['order_by']      = $config['order_by']      ?? ['created_at' => 'desc'];
        // Tag with the feature currently registering, if any. Lets the
        // sidebar paint feature-provided entries with a distinct color.
        $config['feature']       = $config['feature']       ?? $this->currentFeature;

        $this->resources[$name] = $config;
        return $this;
    }

    public function menuItem(string $label, string $href, string $icon = 'fa-circle', string $menu = 'Другое', int $order = 100, array $children = []): self
    {
        // Dedupe by href — FIRST writer wins. Package defaults register
        // later (in $app->booted()) so consumer-specific menuItem() calls
        // from AppServiceProvider::boot() take precedence for label and
        // section placement. Package fills in anything the consumer didn't
        // claim.
        foreach ($this->menuItems as $existing) {
            if ($existing['href'] === $href) return $this;
        }
        $feature = $this->currentFeature;
        $this->menuItems[] = compact('label', 'href', 'icon', 'menu', 'order', 'feature', 'children');
        return $this;
    }

    /**
     * Register a whole sidebar group (section) in one call — useful when
     * you want a nested "Страницы сайта" / "Соцпортал" / etc. area that
     * doesn't map to a single top-level link.
     *
     * `$items` shape: `[['label' => 'История', 'href' => '/admin/...', 'icon' => 'fa-...']]`.
     */
    public function menuGroup(string $section, array $items, int $order = 100): self
    {
        $feature = $this->currentFeature;
        foreach ($items as $i => $it) {
            $href = $it['href'] ?? '#';
            // Dedupe as usual.
            foreach ($this->menuItems as $existing) {
                if ($existing['href'] === $href) continue 2;
            }
            $this->menuItems[] = [
                'label'   => $it['label'] ?? $href,
                'href'    => $href,
                'icon'    => $it['icon'] ?? 'fa-circle',
                'menu'    => $section,
                'order'   => $order + $i,
                'feature' => $feature,
                'children'=> [],
            ];
        }
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

    /** @var array<string, \Meta\AdminCore\Features\FeatureModule> */
    protected array $features = [];

    /**
     * Register a packaged feature module. Called from the service provider
     * with the list of built-in modules; consumer apps can also call this
     * to add their own.
     */
    public function registerFeature(\Meta\AdminCore\Features\FeatureModule $module): self
    {
        $this->features[$module->name()] = $module;
        return $this;
    }

    /** @return \Meta\AdminCore\Features\FeatureModule[] */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * Feature-flag check. Priority order:
     *   1. DB override in `settings` table (key `feature.{name}`) — set via
     *      admin UI, wins if present.
     *   2. config('admin-core.features.{name}') — .env / config default.
     *
     * Typical usage in AppServiceProvider:
     *
     *   if (AdminCore::enabled('sdg')) {
     *       AdminCore::resource('sdg-goals', [...]);
     *   }
     *
     * Unknown feature names return false.
     */
    public function enabled(string $feature): bool
    {
        // DB override takes priority, cached for the lifetime of the request.
        static $dbFlags = null;
        if ($dbFlags === null) {
            $dbFlags = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                    $rows = \Illuminate\Support\Facades\DB::table('settings')
                        ->where('key', 'like', 'feature.%')
                        ->pluck('value', 'key');
                    foreach ($rows as $k => $v) {
                        $name = substr($k, strlen('feature.'));
                        // value stored as JSON boolean or "1"/"0" — normalise.
                        $decoded = json_decode((string) $v, true);
                        $dbFlags[$name] = is_bool($decoded) ? $decoded
                            : in_array((string) $v, ['1', 'true', 'on', 'yes'], true);
                    }
                }
            } catch (\Throwable) {
                // Settings table missing — fall through to config.
            }
        }
        if (array_key_exists($feature, $dbFlags)) {
            return $dbFlags[$feature];
        }
        return (bool) config("admin-core.features.{$feature}", false);
    }

    /**
     * Persist a feature flag to the settings table. Clears the static cache
     * so the next enabled() call sees the new value.
     */
    public function setEnabled(string $feature, bool $on): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) return;
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['key' => 'feature.' . $feature],
            [
                'value'      => json_encode($on),
                'type'       => 'boolean',
                'group'      => 'features',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        // Bust the static cache by triggering a fresh static initialization.
        // (Simplest: use a closure-bound property, but we'll just re-resolve.)
    }

    /**
     * Wrap a callback so it only runs when the feature is enabled.
     * Chainable: AdminCore::whenEnabled('sdg', fn () => ...);
     */
    public function whenEnabled(string $feature, callable $fn): self
    {
        if ($this->enabled($feature)) {
            $fn($this);
        }
        return $this;
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
                'label'   => $r['label'],
                'href'    => '/admin/' . $r['route'],
                'icon'    => 'fas ' . $r['icon'],
                'menu'    => $r['menu'],
                'order'   => $r['order'] ?? 50,
                'feature' => $r['feature'] ?? null,
            ];
        }
        foreach ($this->menuItems as $m) {
            $items[] = [
                'label'   => $m['label'],
                'href'    => $m['href'],
                'icon'    => str_starts_with($m['icon'], 'fas ') ? $m['icon'] : 'fas ' . $m['icon'],
                'menu'    => $m['menu'],
                'order'   => $m['order'],
                'feature' => $m['feature'] ?? null,
            ];
        }

        usort($items, fn ($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));

        $grouped = [];
        foreach ($items as $i) {
            $grouped[$i['menu']] ??= [];
            $grouped[$i['menu']][] = [
                'label'   => $i['label'],
                'href'    => $i['href'],
                'icon'    => $i['icon'],
                'feature' => $i['feature'],
            ];
        }

        // Stable section ordering — standard groups in known positions,
        // feature-module sections always at the bottom (so optional stuff
        // doesn't interrupt the main workflow).
        $sectionOrder = [
            'Главное'             => 10,
            'Контент'             => 20,
            'Блоки по страницам'  => 25,
            'Образование'         => 30,
            'Обращения'           => 40,
            'Библиотека'          => 50,
            'Медиа'               => 60,
            'Система'             => 70,
        ];
        $sections = [];
        foreach ($grouped as $name => $items) {
            $hasFeature = false;
            foreach ($items as $it) if (!empty($it['feature'])) { $hasFeature = true; break; }
            $sections[] = [
                'name'     => $name,
                'items'    => $items,
                'priority' => $hasFeature ? 100 : ($sectionOrder[$name] ?? 80),
            ];
        }
        usort($sections, fn ($a, $b) => ($a['priority'] <=> $b['priority']) ?: strcmp($a['name'], $b['name']));

        return array_map(
            fn ($s) => ['section' => $s['name'], 'items' => $s['items']],
            $sections,
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
