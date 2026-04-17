# meta/admin-core

Headless admin panel core for Laravel sites. Drives a full admin SPA
(Inertia + Vue 3 + Tiptap) from a **declarative Resource API** — new
admin modules are added with a single call in a service provider, with
no controller or Vue page to write for the common case.

```php
AdminCore::resource('articles', [
    'model'        => \App\Models\Article::class,
    'label'        => 'Статьи',
    'menu'         => 'Контент',
    'icon'         => 'fa-newspaper',
    'image_field'  => 'featured_image',
    'translatable' => ['title', 'excerpt', 'content'],
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
```

That call produces:

- Sidebar entry under **Контент**
- `/admin/articles` — paginated list with search
- `/admin/articles/create` — form with locale tabs (RU/KK/EN), Tiptap
  editor for the `content` field, image upload, sidebar with plain
  attributes
- `/admin/articles/{id}/edit` — pre-filled edit form
- Auto-generated validation from types
- CRUD routes dispatched through a single generic controller
- Toggle-publish action

No `ArticleController`. No `Articles/Index.vue`. No
`Articles/Form.vue`. All driven by the config.

## Features

- **Declarative resources** — full CRUD from one config array
- **Translatable fields** with per-locale inputs (RU/KK/EN by default)
- **Typed attributes** (text, email, url, number, date, select,
  boolean, color…) with auto-generated validation
- **Dynamic FK selects** via closure-based `options`
- **Rich-text editing** — Tiptap / ProseMirror (replaces TinyMCE)
- **Image uploads** — single image per resource, with storage, URL
  helpers, delete-on-destroy
- **Pluggable** — per-resource Vue page overrides, custom controllers,
  mixed legacy + declarative resources
- **Sidebar composition** — resources + ad-hoc `menuItem()` entries,
  grouped by section, ordered
- **Dashboard stats** — pluggable card providers
- **Inertia + Vue 3** — modern SPA admin over a Laravel backend
- **Package-discoverable** — drop it in, boot, go

## Installation (short version)

```bash
composer require meta/admin-core:^0.3
php artisan vendor:publish --tag=admin-core-config
```

Then set up Vite, middleware, and an entry point. Full walkthrough:
[docs/installation.md](docs/installation.md).

Register middleware in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \Meta\AdminCore\Http\Middleware\HandleInertiaRequests::class,
]);
```

Vite entry:

```js
import { bootAdminCore } from '@admin-core/admin-spa.js';
import AdminLayout from '@admin-core/layouts/AdminLayout.vue';

const sitePages = import.meta.glob('./admin-spa/pages/**/*.vue');
const corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue');

bootAdminCore({ sitePages, corePages, AdminLayout, title: 'My Admin' });
```

## Documentation

Full reference lives in [`docs/`](docs/README.md):

### Getting started
- [Installation](docs/installation.md)
- [Quickstart: first resource](docs/quickstart.md)

### Core concepts
- [Resource API reference](docs/resources.md)
- [Translatable fields](docs/fields.md)
- [Attribute types](docs/attributes.md)
- [Dynamic FK selects](docs/select-options.md)
- [Images](docs/images.md)
- [Navigation & dashboard](docs/navigation.md)
- [Validation](docs/validation.md)
- [Routing](docs/routing.md)

### Customisation
- [Custom Vue pages](docs/custom-pages.md)
- [Extending the core](docs/extending.md)

### Reference
- [Architecture](docs/architecture.md)
- [Migration from legacy Spa controllers](docs/migration.md)
- [Upgrade guide](docs/upgrade.md)
- [Troubleshooting](docs/troubleshooting.md)

### Contributing
- [Package development](docs/development.md)

## Requirements

| Stack                       | Version                        |
|-----------------------------|--------------------------------|
| PHP                         | `^8.2`                         |
| Laravel                     | `^11.0 \|\| ^12.0`             |
| inertiajs/inertia-laravel   | `^2.0 \|\| ^3.0`               |
| Node.js                     | `^18`                          |
| Vue                         | `^3.4`                         |
| Vite                        | `^5` or `^6`                   |

## Status

v0.x — in active use by META University sites. Config shape is stable
within `0.2+` but breaking changes may still happen at minor-version
bumps until `1.0`.

See [CHANGELOG](CHANGELOG.md) for release history.

## License

Proprietary. Available under the VCS at
[sirserik/meta-admin-core](https://github.com/sirserik/meta-admin-core).
