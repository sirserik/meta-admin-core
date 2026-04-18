<?php

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
