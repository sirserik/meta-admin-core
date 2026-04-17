# meta/admin-core

Headless admin panel core for META sites. Provides a **Resource API** —
new admin modules are registered with a single call, without writing a
controller or Vue pages for the common case.

## Installation

### 1. Add as Composer package (local path for now)

In your consumer app's `composer.json`:

```json
{
  "repositories": [
    { "type": "path", "url": "../meta-admin-core" }
  ],
  "require": {
    "meta/admin-core": "dev-main"
  }
}
```

Then:
```bash
composer require meta/admin-core:dev-main
```

### 2. Install frontend deps

```bash
npm install vue @inertiajs/vue3 @vitejs/plugin-vue \
            @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link \
            @tiptap/extension-image @tiptap/extension-underline \
            @fontsource/inter @fortawesome/fontawesome-free
```

### 3. Publish views + config

```bash
php artisan vendor:publish --tag=admin-core-views
php artisan vendor:publish --tag=admin-core-config
```

### 4. Register HandleInertiaRequests middleware

In `bootstrap/app.php`:
```php
$middleware->web(append: [
    \Meta\AdminCore\Http\Middleware\HandleInertiaRequests::class,
]);
```

### 5. Vite config

```js
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/admin-spa.css', 'resources/js/admin-spa.js'],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@admin-core': '/vendor/meta/admin-core/resources/js',
        },
    },
});
```

### 6. Consumer `resources/js/admin-spa.js`

```js
import { bootAdminCore } from '@admin-core/admin-spa.js';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const sitePages = import.meta.glob('./admin-spa/pages/**/*.vue');
const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue');

bootAdminCore({ sitePages, corePages, AdminLayout, title: 'META Admin' });
```

## Usage

Register resources in your `AppServiceProvider::boot()`:

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::resource('articles', [
    'model'         => \App\Models\Article::class,
    'label'         => 'Статьи',
    'menu'          => 'Контент',
    'icon'          => 'fa-newspaper',
    'translatable'  => ['title', 'excerpt', 'content'],
    'plain'         => ['slug', 'category', 'is_published', 'is_featured', 'published_at'],
    'image_field'   => 'featured_image',
    'fields'        => [
        ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
        ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
    ],
]);

AdminCore::menuItem('Бэкапы', '/admin/backup', 'fa-database', 'Система');

AdminCore::dashboardStat(fn () => [
    'label' => 'Статьи',
    'value' => \App\Models\Article::count(),
    'icon'  => 'fa-newspaper',
]);
```

Routes, sidebar navigation, CRUD controller and Vue pages are wired
automatically. Custom pages go in `resources/js/admin-spa/pages/{Resource}/Index.vue`
to override the generic templates.

## Architecture

```
meta/admin-core/                  <- this package
├── src/
│   ├── AdminCore.php              <- registry singleton
│   ├── Facades/AdminCore.php
│   ├── AdminCoreServiceProvider.php
│   └── Http/
│       ├── Controllers/
│       │   ├── DashboardController.php
│       │   └── ResourceController.php   <- generic CRUD
│       └── Middleware/
│           └── HandleInertiaRequests.php
├── routes/admin.php                    <- auto-loaded
├── resources/
│   ├── views/app.blade.php             <- Inertia root
│   ├── js/
│   │   ├── admin-spa.js                <- bootAdminCore()
│   │   ├── layouts/AdminLayout.vue
│   │   ├── components/
│   │   │   ├── RichTextEditor.vue      <- Tiptap
│   │   │   ├── TranslatableField.vue
│   │   │   ├── LocaleTabs.vue
│   │   │   ├── PageHeader.vue
│   │   │   └── Pagination.vue
│   │   └── pages/
│   │       ├── Dashboard.vue
│   │       └── Resource/
│   │           ├── Index.vue           <- generic list
│   │           └── Form.vue            <- generic create/edit
│   └── css/admin-spa.css
└── config/admin-core.php               <- publishable config
```
