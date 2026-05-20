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
    | Public routes (opt-out)
    |--------------------------------------------------------------------------
    |
    | Each entry toggles a public-facing route shipped with the package.
    | Defaults to `true` so the package stays additive out of the box.
    |
    | The package also auto-skips a route if the consumer already registered
    | one at the same URI — so most consumer sites don't need to touch this
    | section at all. Set explicitly to `false` if you want to be loud about
    | the decision (e.g. consumer defines its own /sitemap.xml).
    |
    */
    'routes' => [
        'media'       => env('ADMIN_CORE_ROUTE_MEDIA',       true),
        'sitemap'     => env('ADMIN_CORE_ROUTE_SITEMAP',     true),
        'forms'       => env('ADMIN_CORE_ROUTE_FORMS',       true),
        'content_api' => env('ADMIN_CORE_ROUTE_CONTENT_API', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image upload endpoint used by Tiptap (upload-handler).
    | Route must exist in the consumer app and accept multipart/form-data 'file'.
    |--------------------------------------------------------------------------
    */
    'upload_url' => '/admin/upload/image',

    /*
    |--------------------------------------------------------------------------
    | Off-band admin password recovery (PIN-gated)
    |--------------------------------------------------------------------------
    |
    | Two-step rescue flow for admins locked out of the normal mail-based
    | password reset. Set ADMIN_RESET_PIN to a long random string in .env
    | to enable — when blank the /admin/recover routes 404, so the feature
    | isn't advertised on servers that don't want it.
    |
    |   1. GET  {prefix}/recover          → PIN form
    |   2. POST {prefix}/recover          → verify PIN, stash verified-at
    |                                       in the session for `verified_ttl`
    |   3. GET  {prefix}/recover/password → new-password form (gated)
    |   4. POST {prefix}/recover/password → update user, consume session flag
    |
    | Security:
    |   - constant-time PIN compare (hash_equals over sha256)
    |   - per-IP rate limit on both POSTs (see `admin-recovery` limiter)
    |   - minimum PIN length (8) checked at feature-gate time
    |   - optional role allowlist (default admin/super-admin) so the flow
    |     can only reset privileged accounts
    |   - every attempt (success + failure) logged via the default channel
    |
    */
    'recovery' => [
        'pin'            => env('ADMIN_RESET_PIN'),
        'min_pin_length' => 8,
        'verified_ttl'   => (int) env('ADMIN_RESET_VERIFIED_TTL', 300),
        'pin_attempts'   => (int) env('ADMIN_RESET_PIN_ATTEMPTS', 5),
        'pin_decay'      => (int) env('ADMIN_RESET_PIN_DECAY', 3600),
        'password_min'   => (int) env('ADMIN_RESET_PASSWORD_MIN', 12),
        'allowed_roles'  => ['admin', 'super-admin'],
    ],

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
        'sdg'           => env('FEATURE_SDG',          false),
        'green_deal'    => env('FEATURE_GREEN_DEAL',   false),
        'procurements'  => env('FEATURE_PROCUREMENTS', false),
        'library'       => env('FEATURE_LIBRARY',      false),
        'catalog'       => env('FEATURE_CATALOG',      false),
        'projects'      => env('FEATURE_PROJECTS',     false),
        'redirects'     => env('FEATURE_REDIRECTS',    true),

        // Inbox
        'leads'             => env('FEATURE_LEADS',             true),
        'rector_questions'  => env('FEATURE_RECTOR_QUESTIONS',  false),
    ],
];
