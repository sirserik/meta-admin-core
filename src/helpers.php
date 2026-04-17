<?php

if (!function_exists('admin_core_route')) {
    /**
     * Build a URL to a resource action: edit, index, destroy…
     *
     *   admin_core_route('articles', 'edit', $id)
     *   admin_core_route('programs', 'index')
     */
    function admin_core_route(string $resource, string $action, ...$params): string
    {
        return route("admin.resource.{$action}", array_merge([$resource], $params));
    }
}
