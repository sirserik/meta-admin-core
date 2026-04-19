# 01. Быстрый старт

Инструкция «с нуля до работающей админки» для нового Laravel-проекта.

## 0. Предусловия

- Laravel 11 или 12.
- PHP 8.2+.
- База данных (SQLite / MySQL / PostgreSQL).
- Возможность подключиться к приватному GitHub-репозиторию пакета (токен
  или deploy key).

## 1. Добавить пакет в composer

```bash
composer require meta/admin-core:^0.43
```

Если пакет приватный, сначала добавь VCS-репозиторий в `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/sirserik/meta-admin-core.git"
        }
    ]
}
```

Если прод не может авторизоваться на GitHub, см.
[траблшутинг](./29-troubleshooting.md#github-auth).

## 2. Настроить composer-скрипты (рекомендуется)

В `composer.json` рекомендуется добавить post-install хук для миграций и
сброса кэша:

```json
{
    "scripts": {
        "post-install-cmd": [
            "@php artisan migrate --force --ansi",
            "@php artisan view:clear --ansi"
        ]
    }
}
```

Это нужно, чтобы на Plesk-деплоях миграции накатывались автоматически.

## 3. Опубликовать и настроить конфиг

```bash
php artisan vendor:publish --tag=admin-core-config
```

Появится `config/admin-core.php`. Минимальный набор, что стоит задать:

```php
return [
    'prefix'     => 'admin',
    'middleware' => ['auth', 'verified'],
    'brand'      => [
        'name'  => env('ADMIN_BRAND_NAME', 'ETEC'),
        'color' => env('ADMIN_BRAND_COLOR', '#C41E3A'),
    ],
    'locales'    => ['ru', 'kk', 'en'],
    'upload_url' => '/admin/upload/image',
];
```

## 4. Накатить миграции

```bash
php artisan migrate
```

Подтянутся:
- `page_blocks`
- `menu_items`
- `translations`
- `settings`
- `media`
- `leads`
- `revisions`
- `webhooks`
- `taxonomy_terms` + `taxonomy_term_model`
- `forms` + `form_submissions`

## 5. Создать первого пользователя

Через `php artisan tinker`:

```php
\App\Models\User::create([
    'name'     => 'Admin',
    'email'    => 'admin@example.com',
    'password' => bcrypt('secret'),
    'email_verified_at' => now(),
]);
```

## 6. Зарегистрировать ресурсы

Создай `App\Providers\AdminResourceServiceProvider` и зарегистрируй там
модели через `AdminCore::resource(...)`:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Meta\AdminCore\Facades\AdminCore;

class AdminResourceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!class_exists(AdminCore::class)) return;

        AdminCore::resource('articles', [
            'model'         => \App\Models\Article::class,
            'label'         => 'Статьи',
            'menu'          => 'Контент',
            'icon'          => 'fa-newspaper',
            'translatable'  => ['title', 'excerpt', 'content'],
            'fields' => [
                ['name' => 'title',   'type' => 'text',     'label' => 'Заголовок', 'required' => true],
                ['name' => 'excerpt', 'type' => 'textarea', 'label' => 'Краткое описание'],
                ['name' => 'content', 'type' => 'editor',   'label' => 'Содержимое'],
            ],
            'attributes' => [
                ['name' => 'slug',         'type' => 'text',    'label' => 'Slug'],
                ['name' => 'published_at', 'type' => 'date',    'label' => 'Опубликовано'],
                ['name' => 'is_featured',  'type' => 'boolean', 'label' => 'В топе'],
            ],
        ]);
    }
}
```

Добавь провайдер в `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AdminResourceServiceProvider::class,
];
```

Подробнее о том, какие опции поддерживаются, см. [04. Ресурсы](./04-resources.md).

## 7. Настроить Inertia

Пакет требует, чтобы в приложении был подключён Inertia. Если новый
проект:

```bash
composer require inertiajs/inertia-laravel
php artisan inertia:middleware
```

Добавь `HandleInertiaRequests::class` в `web`-группу middleware.

## 8. Настроить Vite

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin-spa.css',
                'resources/js/admin-spa.js',
            ],
        }),
        vue(),
    ],
    resolve: {
        preserveSymlinks: true,
        alias: {
            '@admin-core': '/vendor/meta/admin-core/resources/js',
        },
    },
});
```

И главный `resources/js/admin-spa.js`:

```js
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob(
            '../../vendor/meta/admin-core/resources/js/pages/**/*.vue',
            { eager: true },
        );
        return pages[`../../vendor/meta/admin-core/resources/js/pages/${name}.vue`];
    },
    setup: ({ el, App, props, plugin }) =>
        createApp({ render: () => h(App, props) }).use(plugin).mount(el),
});
```

Собери: `npm install && npm run build`.

## 9. Проверить

```bash
php artisan serve
# → открой http://localhost:8000/admin
```

Залогинься под созданным пользователем. Должен появиться дашборд и список
ресурсов в сайдбаре.

## 10. Настроить cron для scheduled publishing

Добавь в `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('admin-core:apply-schedule')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

И системный cron (Plesk / crontab):

```
* * * * * cd /path/to/project && php artisan schedule:run >/dev/null 2>&1
```

## 11. Как дальше

- Подробнее о ресурсах → [04. Ресурсы](./04-resources.md)
- Подключить автоматические ревизии → [11. Revisionable](./11-revisionable.md)
- Включить webhooks → [13. Webhookable](./13-webhookable.md)
- Собрать публичный фронтенд → [Blade-рендеринг блоков](./08-page-builder.md)
