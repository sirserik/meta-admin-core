<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin URL prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => 'admin',

    /*
    |--------------------------------------------------------------------------
    | Middleware for admin routes
    |--------------------------------------------------------------------------
    */
    'middleware' => ['auth', 'verified'],

    /*
    |--------------------------------------------------------------------------
    | Branding — name & primary color shown in sidebar/header
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'name'      => env('ADMIN_BRAND_NAME', 'Admin'),
        'subtitle'  => env('ADMIN_BRAND_SUBTITLE', ''),
        'color'     => env('ADMIN_BRAND_COLOR', '#C41E3A'),
        'logo_char' => env('ADMIN_BRAND_LOGO_CHAR', 'A'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales (primary first)
    |--------------------------------------------------------------------------
    */
    'locales' => ['ru', 'kk', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Image upload endpoint used by Tiptap (upload-handler).
    | Route must exist in the consumer app and accept multipart/form-data 'file'.
    |--------------------------------------------------------------------------
    */
    'upload_url' => '/admin/upload/image',
];
