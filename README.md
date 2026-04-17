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
    'image_field'   => 'featured_image',
    'translatable'  => ['title', 'excerpt', 'content'],
    'fields' => [
        ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
        ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
        ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
    ],
    'attributes' => [
        ['name' => 'slug',         'type' => 'text',           'label' => 'Slug'],
        ['name' => 'is_published', 'type' => 'boolean',        'label' => 'Опубликована'],
        ['name' => 'published_at', 'type' => 'datetime-local', 'label' => 'Дата публикации'],
    ],
]);

AdminCore::menuItem('Бэкапы', '/admin/backup', 'fa-database', 'Система');

AdminCore::dashboardStat(fn () => [
    'label' => 'Статьи',
    'value' => \App\Models\Article::count(),
    'icon'  => 'fa-newspaper',
]);
```

Routes, sidebar navigation, CRUD controller, validation rules and Vue
pages are wired automatically. The resource URL is `/admin/{name}` and
catches all `GET|POST|PUT|PATCH|DELETE` — no per-resource routes to
register. To override the generic Vue page, add
`resources/js/admin-spa/pages/{PageKey}/{Index,Form}.vue` and point
`'page' => 'PageKey'` in the config.

### Config reference

| Key             | Type     | Default         | Purpose                                           |
|-----------------|----------|-----------------|---------------------------------------------------|
| `model`         | class    | required        | Eloquent model class                              |
| `label`         | string   | `ucfirst(name)` | Sidebar link text, page header                    |
| `menu`          | string   | `'Контент'`     | Sidebar section grouping                          |
| `icon`          | string   | `'fa-file'`     | FontAwesome class (without `fas` prefix)          |
| `image_field`   | string   | `null`          | Column name holding image path (with `_url` accessor) |
| `translatable`  | string[] | `[]`            | Field names stored via Spatie Translatable        |
| `fields`        | array[]  | `[]`            | Translatable form fields (main area)              |
| `attributes`    | array[]  | `[]`            | Plain (non-translatable) form fields (sidebar)    |
| `order_by`      | array    | `['created_at' => 'desc']` | Default list ordering              |
| `per_page`      | int      | `15`            | List pagination                                   |
| `route_key`     | string   | model default   | Model key used in URLs (e.g. `'slug'`)            |
| `page`          | string   | `'Resource'`    | Vue page folder (`Resource/Index.vue` etc.)       |

### Field types (translatable — `fields`)

`text`, `textarea`, `editor` (Tiptap rich-text). Each locale (`ru/kk/en`)
gets its own input, switched by `LocaleTabs`.

### Attribute types (plain — `attributes`)

| Type              | Renders as                   | Validation rule added    |
|-------------------|------------------------------|--------------------------|
| `text`            | `<input type="text">`        | `string`, `max:{max}`    |
| `textarea`        | `<textarea>`                 | `string`                 |
| `email`           | `<input type="email">`       | `email`                  |
| `url`             | `<input type="url">`         | `url`, `max:{max}`       |
| `number`          | `<input type="number">`      | `numeric`                |
| `date`            | `<input type="date">`        | `date`                   |
| `datetime-local`  | `<input type="datetime-local">` | `date`                |
| `color`           | `<input type="color">`       | `string`                 |
| `boolean`         | `<input type="checkbox">`    | `boolean`                |
| `select`          | `<select>`                   | `in:<option values>`     |

Per-attribute modifiers: `required`, `unique`, `max`, `placeholder`,
`help`, `options` (for `select`).

### Dynamic FK selects (closure-based `options`)

`options` can be a closure — it's evaluated on each request so the list
stays fresh:

```php
AdminCore::resource('teachers', [
    'model' => Teacher::class,
    // ...
    'attributes' => [
        ['name' => 'school_id', 'type' => 'select', 'required' => true,
            'options' => fn () => School::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
                ->all()],
    ],
]);
```

### Unique validation

```php
['name' => 'slug', 'type' => 'text', 'unique' => true],
```

Generates `unique:{table},{column},{currentId}` — excludes current row on
update automatically.

## Migration from legacy Spa controllers

If you already have `App\Http\Controllers\Admin\Spa\FooController extends
BaseCrudController`, migration is usually a 1:1 translation:

1. Remove the explicit `/foo` routes from your route file.
2. Delete the controller + its Vue pages.
3. Add `AdminCore::resource('foo', [...])` to `AppServiceProvider`.

The catch-all `/admin/{resource}` route will take over. Named routes like
`route('admin.foo.index')` will no longer resolve — replace with plain
`url('/admin/foo')` or `/admin/foo`.

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
