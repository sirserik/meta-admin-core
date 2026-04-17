# Installation

Set up `meta/admin-core` in a Laravel 11/12 app. The package assumes
you'll drive the admin with **Inertia.js + Vue 3**.

## Requirements

| Stack               | Version                                 |
|---------------------|-----------------------------------------|
| PHP                 | `^8.2`                                  |
| Laravel             | `^11.0 \|\| ^12.0`                      |
| inertiajs/inertia-laravel | `^2.0 \|\| ^3.0`                  |
| Node.js             | `^18`                                   |
| Vue                 | `^3.4`                                  |
| Vite                | `^5` or `^6`                            |

Database: SQLite, MySQL/MariaDB, PostgreSQL — anything Laravel supports.
Spatie Translatable is needed if you use `translatable` fields.

## 1. Require the package

Add the repository and require the package:

```json
// composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/sirserik/meta-admin-core.git"
        }
    ],
    "require": {
        "meta/admin-core": "^0.3"
    }
}
```

```bash
composer update meta/admin-core -W
```

### Local development (path repo)

If you're working on the package in parallel with a consumer app, add a
path repo alongside the VCS one:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "/absolute/path/to/meta-admin-core",
            "options": {
                "symlink": true,
                "canonical": false,
                "versions": { "meta/admin-core": "0.3.0" }
            }
        },
        { "type": "vcs", "url": "https://github.com/sirserik/meta-admin-core.git" }
    ]
}
```

`canonical: false` lets Composer fall back to the VCS repo on servers
where the path doesn't exist.

## 2. Install Node dependencies

```bash
npm install vue @inertiajs/vue3 @vitejs/plugin-vue \
            @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link \
            @tiptap/extension-image @tiptap/extension-underline \
            @fontsource/inter @fortawesome/fontawesome-free
```

Tailwind is expected in the consumer app (v3 or v4 both work).
`admin-core.css` in the package brings zero Tailwind directives, so you
can style over it freely.

## 3. Publish config and views (optional)

```bash
# config/admin-core.php — prefix, middleware, branding, locales, upload URL
php artisan vendor:publish --tag=admin-core-config

# Root app.blade.php — if you want to customise the Inertia root
php artisan vendor:publish --tag=admin-core-views
```

## 4. Register the Inertia middleware

In `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \Meta\AdminCore\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ...
```

This middleware:

- Sets `admin-core::app` as the Inertia root view.
- Shares `auth`, `flash`, `locale`, `navigation`, `brand` to every page.

Or extend it if you need to share more props:

```php
// app/Http/Middleware/HandleInertiaRequests.php
use Meta\AdminCore\Http\Middleware\HandleInertiaRequests as Base;

class HandleInertiaRequests extends Base {
    public function share(Request $request): array {
        return [
            ...parent::share($request),
            'siteSettings' => fn () => SiteSettings::cached(),
        ];
    }
}
```

Register your subclass in `bootstrap/app.php` instead.

## 5. Configure Vite

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/admin-spa.css',
                'resources/js/admin-spa.js',
                'resources/css/app.css',   // public site
                'resources/js/app.js',     // public site
            ],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        // Crucial when admin-core is symlinked via path repo
        preserveSymlinks: true,
        alias: {
            '@admin-core': '/vendor/meta/admin-core/resources/js',
        },
    },
});
```

`preserveSymlinks: true` stops Vite from resolving through the symlink,
which would cause duplicate Vue instances.

## 6. Entry point

`resources/js/admin-spa.js`:

```js
import { bootAdminCore } from '@admin-core/admin-spa.js';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

// Site-level Vue pages (for overrides and custom screens)
const sitePages = import.meta.glob('./admin-spa/pages/**/*.vue');

// Package's built-in pages (Dashboard, Resource/Index, Resource/Form)
const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue');

bootAdminCore({
    sitePages,
    corePages,
    AdminLayout,
    title: 'META University',   // shown in browser tab
});
```

Resolution order: **site pages win over core pages**, so placing a file
at `resources/js/admin-spa/pages/Articles/Index.vue` overrides the
generic `Resource/Index.vue` when a resource is registered with
`page: 'Articles'`.

## 7. CSS entry point

`resources/css/admin-spa.css`:

```css
@import 'tailwindcss';  /* v4; use @tailwind base/components/utilities; for v3 */
```

Add your own tweaks here. The package doesn't ship Tailwind config — use
your site's existing one.

## 8. Verify it boots

```bash
php artisan serve
npm run dev
```

Visit `/admin` — you should see the dashboard with the sidebar (empty
until you register a resource).

### Seeding the first user

If your app's auth uses Laravel Breeze / Jetstream / Fortify the default
`/register` works. Otherwise create a user via Tinker:

```bash
php artisan tinker
>>> User::create(['name' => 'Admin', 'email' => 'you@example.com', 'password' => bcrypt('secret')]);
```

Login, then visit `/admin`. You're in.

---

Next: [Quickstart — register your first resource →](quickstart.md)
