<?php

if (!function_exists('page_blocks')) {
    /**
     * Terse Statamic-style accessor for augmented page blocks.
     *
     *   page_blocks('home')['hero']?->title          // array access, auto-materialized
     *   page_blocks('home')->block('hero')?->title   // explicit single-block shortcut
     *   @foreach (page_blocks('home') as $b) ... @endforeach
     *
     *   // Full collection (keyed by block_key) for advanced ops
     *   page_blocks('home')->get()->pluck('title');
     *
     * Returns the resolver so you can chain `locale()`, `withDrafts()`,
     * `only()`, `types()` before accessing results. The resolver itself
     * implements ArrayAccess + IteratorAggregate, so templates rarely need
     * to call `->get()` explicitly.
     */
    function page_blocks(string $page): \Meta\AdminCore\Content\PageBlockResolver
    {
        return \Meta\AdminCore\Content\PageBlockResolver::forPage($page);
    }
}

if (!function_exists('page_block')) {
    /**
     * Even terser for single-block access:
     *
     *   page_block('home', 'hero')?->title
     */
    function page_block(string $page, string $key): ?\Meta\AdminCore\Content\PresentedBlock
    {
        return \Meta\AdminCore\Content\PageBlockResolver::forPage($page)->block($key);
    }
}

if (!function_exists('hero_buttons')) {
    /**
     * Resolve a hero block's `buttons[]` array for rendering in the
     * current (or given) locale. Filters out buttons without text or
     * url. `text` may be a plain string or a `{locale: value}` map.
     *
     *   @foreach (hero_buttons($heroData) as $btn)
     *     <a href="{{ $btn['url'] }}" target="{{ $btn['target'] }}"
     *        class="{{ $btn['style'] }}">
     *         @if($btn['icon']) <i class="{{ $btn['icon'] }}"></i> @endif
     *         {{ $btn['text'] }}
     *     </a>
     *   @endforeach
     *
     * For positional layouts (corners / edges), use `hero_buttons_zones`
     * to pick buttons per zone.
     */
    function hero_buttons(array $data, ?string $locale = null): array
    {
        $locale   = $locale ?? app()->getLocale();
        $fallback = config('app.fallback_locale', 'ru');
        $out      = [];

        foreach (($data['buttons'] ?? []) as $b) {
            if (!is_array($b)) continue;

            $raw = $b['text'] ?? null;
            if (is_array($raw)) {
                $text = $raw[$locale] ?? $raw[$fallback] ?? null;
                if ($text === null) {
                    foreach ($raw as $v) { if (!empty($v)) { $text = $v; break; } }
                }
            } else {
                $text = $raw;
            }

            if (empty($text) || empty($b['url'])) continue;

            $out[] = [
                'text'     => (string) $text,
                'url'      => (string) $b['url'],
                'icon'     => $b['icon']     ?? null,
                'style'    => $b['style']    ?? null,
                'target'   => $b['target']   ?? '_self',
                'position' => $b['position'] ?? 'center',
            ];
        }
        return $out;
    }
}

if (!function_exists('hero_buttons_zones')) {
    /**
     * Same as `hero_buttons`, but grouped by the `position` field so the
     * blade can lay out each zone independently (corners with `absolute`,
     * center in the normal flow, etc.). Returns an array keyed by the
     * 9 supported zones — unknown / missing positions fall into `center`.
     *
     *   $zones = hero_buttons_zones($heroData);
     *   @foreach ($zones['top-right'] ?? [] as $btn) ... @endforeach
     */
    function hero_buttons_zones(array $data, ?string $locale = null): array
    {
        $allowed = [
            'top-left', 'top-center', 'top-right',
            'center-left', 'center', 'center-right',
            'bottom-left', 'bottom-center', 'bottom-right',
        ];

        $zones = array_fill_keys($allowed, []);
        foreach (hero_buttons($data, $locale) as $b) {
            $pos = in_array($b['position'], $allowed, true) ? $b['position'] : 'center';
            $zones[$pos][] = $b;
        }
        return $zones;
    }
}

if (!function_exists('media_url')) {
    /**
     * Build a public URL to a storage file.
     *
     * Plesk/Nginx sometimes intercepts `/storage/` as a static-file
     * location and bypasses Laravel's routing. We route through `/media/`
     * instead (served by a package fallback route) so the same URL
     * works whether Nginx serves it directly from `public/media/` or
     * Laravel streams it from `storage/app/public/`.
     *
     *   media_url('management/1.jpg') => https://host/media/management/1.jpg
     */
    function media_url(string $path): string
    {
        $path = preg_replace('#^/?storage/#', '', $path);
        $parts = array_map('rawurlencode', explode('/', $path));
        return url('media/' . implode('/', $parts));
    }
}

if (!function_exists('admin_core_route')) {
    /**
     * Build a URL to a resource action: edit, index, destroy…
     *
     *   admin_core_route('articles', 'edit', $id)    => /admin/articles/{id}/edit
     *   admin_core_route('programs', 'index')         => /admin/programs
     *   admin_core_route('news',     'create')        => /admin/news/create
     *   admin_core_route('pages',    'update',  $id)  => /admin/pages/{id}
     *   admin_core_route('contacts', 'destroy', $id)  => /admin/contacts/{id}
     */
    function admin_core_route(string $resource, string $action, ...$params): string
    {
        $prefix = config('admin-core.prefix', 'admin');
        $base   = '/' . trim($prefix, '/') . '/' . $resource;
        return match ($action) {
            'index'          => $base,
            'create'         => "$base/create",
            'store'          => $base,
            'edit'           => "$base/{$params[0]}/edit",
            'update',
            'destroy'        => "$base/{$params[0]}",
            'toggle-publish' => "$base/{$params[0]}/toggle-publish",
            default          => $base,
        };
    }
}
