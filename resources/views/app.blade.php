<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('admin-core.brand.name', 'Admin') }}</title>

    @vite(['resources/css/admin-spa.css', 'resources/js/admin-spa.js'])
    @inertiaHead
</head>
<body class="antialiased bg-gray-50 dark:bg-gray-900">
    @inertia
</body>
</html>
