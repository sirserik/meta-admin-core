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

    /*
    |--------------------------------------------------------------------------
    | Feature toggles — enable/disable optional admin modules per site.
    |
    | Consumer apps read these in AppServiceProvider::registerAdminResources()
    | to conditionally register resources / menu items / dashboard stats:
    |
    |   if (AdminCore::enabled('sdg')) {
    |       AdminCore::resource('sdg-goals', [...]);
    |       AdminCore::resource('sdg-news',  [...]);
    |   }
    |
    | Override per environment via .env:
    |   FEATURE_SDG=true
    |   FEATURE_GREEN_DEAL=false
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Core content modules
        'news'      => env('FEATURE_NEWS',       true),
        'articles'  => env('FEATURE_ARTICLES',   true),
        'pages'     => env('FEATURE_PAGES',      true),
        'blocks'    => env('FEATURE_BLOCKS',     true),

        // Education
        'schools'   => env('FEATURE_SCHOOLS',    true),
        'programs'  => env('FEATURE_PROGRAMS',   true),
        'teachers'  => env('FEATURE_TEACHERS',   true),
        'management'=> env('FEATURE_MANAGEMENT', true),
        'vacancies' => env('FEATURE_VACANCIES',  true),

        // Optional extensions
        'sdg'         => env('FEATURE_SDG',        false),
        'green_deal'  => env('FEATURE_GREEN_DEAL', false),
        'library'     => env('FEATURE_LIBRARY',    false),
        'catalog'     => env('FEATURE_CATALOG',    false),
        'projects'    => env('FEATURE_PROJECTS',   false),
        'redirects'   => env('FEATURE_REDIRECTS',  true),

        // Inbox
        'leads'             => env('FEATURE_LEADS',             true),
        'rector_questions'  => env('FEATURE_RECTOR_QUESTIONS',  false),
    ],
];
