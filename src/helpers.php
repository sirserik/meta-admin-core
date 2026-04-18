<?php

if (!function_exists('page_blocks')) {
    /**
     * Terse Statamic-style accessor for augmented page blocks.
     *
     *   page_blocks('home')->get('hero')->title
     *   page_blocks('sdg-resources')->get('main_links')->links
     *
     * Returns the resolver so you can chain `locale()`, `withDrafts()`,
     * `only()`, `types()` before `get()` / `block()`.
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
