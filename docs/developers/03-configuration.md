# 03. Конфигурация

Все настройки пакета — в `config/admin-core.php`. Публикуется через:

```bash
php artisan vendor:publish --tag=admin-core-config
```

## Полный список опций

```php
<?php

return [

    // URL-префикс для всех админ-роутов. Все защищённые страницы
    // монтируются под /{prefix}/…
    'prefix' => 'admin',

    // Middleware, которое оборачивает группу админ-роутов. 'web' добавляется
    // автоматически — гарантирует сессии/CSRF/cookies.
    'middleware' => ['auth', 'verified'],

    // Брендинг в сайдбаре и при логине.
    'brand' => [
        'name'      => env('ADMIN_BRAND_NAME', 'Admin'),
        'subtitle'  => env('ADMIN_BRAND_SUBTITLE', ''),
        'color'     => env('ADMIN_BRAND_COLOR', '#C41E3A'),
        'logo_char' => env('ADMIN_BRAND_LOGO_CHAR', 'A'),
    ],

    // Поддерживаемые локали. Первая — основная: в неё пишется колонка
    // title/content, остальные — в таблицу translations.
    'locales' => ['ru', 'kk', 'en'],

    // URL, куда Tiptap отправляет картинки при загрузке.
    'upload_url' => '/admin/upload/image',

    // Модули-фичи. Можно выключать целиком (SDG-портал, Green Deal Center).
    // См. "Feature Modules" для деталей.
    'features' => [
        // 'sdg'         => ['enabled' => env('ADMIN_FEATURE_SDG', false)],
        // 'green_deal'  => ['enabled' => env('ADMIN_FEATURE_GDC', false)],
    ],

    // Sitemap.xml: настройки кэширования.
    'sitemap' => [
        'ttl'       => env('ADMIN_SITEMAP_TTL', 3600),
        'cache_key' => 'admin-core.sitemap.xml',
    ],
];
```

## По разделам

### `prefix`

Префикс URL для всех админ-роутов:

```php
'prefix' => 'admin',  // → /admin/articles, /admin/blocks, …
'prefix' => 'cms',    // → /cms/articles, /cms/blocks, …
```

Меняй, если у тебя конфликт с существующим роутом `/admin/*` в публичной
части.

### `middleware`

Список middleware-алиасов, которые оборачивают **все** админ-роуты.

```php
'middleware' => ['auth', 'verified'],              // default
'middleware' => ['auth', 'verified', 'admin-gate'], // свой gate
'middleware' => ['auth:sanctum', 'verified'],       // API-auth
```

Всегда добавляется `web` автоматически.

### `brand`

Управляет видом сайдбара/header'а:

- **`name`** — большой заголовок в сайдбаре.
- **`subtitle`** — мелким шрифтом под именем (опц.).
- **`color`** — hex, primary-цвет (используется в кнопках, ссылках).
- **`logo_char`** — однобуквенный логотип в квадратике.

Пример для ETEC:

```php
ADMIN_BRAND_NAME=ETEC
ADMIN_BRAND_SUBTITLE="Евразийский технико-экономический колледж"
ADMIN_BRAND_COLOR=#C41E3A
ADMIN_BRAND_LOGO_CHAR=E
```

### `locales`

Критически важно: первая локаль становится **основной**. В неё пишется
исходная колонка (`title`, `content`, `subtitle`), остальные — в
`translations` по схеме (translatable_type, translatable_id, locale, field, value).

**Поддержка N локалей**: можно указать любое количество. Пакет нигде
не завязан на `['ru','kk','en']`:

```php
'locales' => ['en'],                          // монолингвальный
'locales' => ['ru'],                          // только русский
'locales' => ['en', 'fr', 'es', 'de'],        // четыре языка
```

### `upload_url`

Куда Tiptap отправляет POST с загружаемой картинкой при клике на 📷.
Маршрут должен принимать `multipart/form-data` с полем `file` и
возвращать JSON `{url}`:

```json
{ "url": "https://example.com/storage/editor/1234567890_image.webp" }
```

По умолчанию — `/admin/upload/image`, у пакета есть встроенный
`UploadController@uploadImage`, но консьюмер может переопределить.

### `features`

Опциональные **модули** — куски функционала, которые можно включать/
выключать через админку или env:

```php
'features' => [
    'sdg' => ['enabled' => true],  // модуль SDG-портала
    'greendeal' => ['enabled' => false],
],
```

См. [20. Feature Modules](./20-feature-modules.md).

### `sitemap`

```php
'sitemap' => [
    'ttl'       => 3600,                 // seconds; 0 = не кэшировать
    'cache_key' => 'admin-core.sitemap.xml',
],
```

Кэш ложится в cache-драйвер приложения. См. [15. Sitemap](./15-sitemap.md).

## Переменные окружения

Рекомендованный минимум в `.env`:

```env
# Брендинг
ADMIN_BRAND_NAME=ETEC
ADMIN_BRAND_COLOR=#C41E3A

# Sitemap
ADMIN_SITEMAP_TTL=3600

# Если используешь feature toggles
ADMIN_FEATURE_SDG=false
ADMIN_FEATURE_GDC=false
```

## Design-tokens (theme)

Отдельный конфиг `config/theme.php` — набор CSS-переменных для фронта:

```bash
php artisan vendor:publish --tag=admin-core-theme-config
```

См. [21. Theme](./21-theme.md).

## Следующее

→ [04. Регистрация ресурсов](./04-resources.md)
