# 14. Read-only Content API

JSON-эндпоинты для внешних фронтендов (Next.js, мобильное приложение,
SSG, headless-сайт).

## Базовый URL

Монтируется в `routes/public.php` пакета — без префикса, без auth.

```
GET /api/content/pages/{slug}
GET /api/content/{resource}
GET /api/content/{resource}/{id_or_slug}
```

## `/api/content/pages/{slug}`

Блоки страницы (только `active=true` + `status=published`).

```
GET /api/content/pages/home
```

Response:

```json
{
    "page": { "slug": "home", "locale": "ru" },
    "blocks": [
        {
            "id": 143,
            "block_type": "hero",
            "block_key": "hero_main",
            "title": "Добро пожаловать",
            "subtitle": "Лучшее образование",
            "content": "<p>…</p>",
            "data": {
                "background": "/media/hero.webp",
                "buttons": [ {"text": "Поступить", "url": "/admission"} ]
            },
            "settings": {},
            "sort_order": 0
        },
        …
    ]
}
```

## `/api/content/{resource}`

Пагинированный список зарегистрированного ресурса.

```
GET /api/content/articles?page=2&per_page=20&locale=kk
```

Response:

```json
{
    "data": [
        {
            "id": 14,
            "slug": "novosti-nauki",
            "title": "Ғылымдағы жаңалықтар",
            "excerpt": "…",
            "…": "…",
            "terms": {
                "tag": [{"slug": "science", "label": "Ғылым"}]
            }
        }
    ],
    "meta": {
        "current_page": 2,
        "per_page":     20,
        "total":        87,
        "last_page":    5,
        "locale":       "kk"
    }
}
```

### Query-параметры

- `page` — номер страницы (default 1).
- `per_page` — размер страницы (default 20, max 100).
- `locale` — локаль для переводимых полей (default из `Accept-Language`
  → `config('app.locale')` → первая из `admin-core.locales`).
- `tag=slug1,slug2` — фильтр по тегу (только если модель `Taxable`).
- `category=slug` — фильтр по категории.

## `/api/content/{resource}/{id_or_slug}`

Одна запись. Если модель имеет колонку `slug`, можно использовать её;
иначе — числовой ID.

```
GET /api/content/articles/novosti-nauki
GET /api/content/articles/14
```

Response:

```json
{
    "data": {
        "id": 14,
        "slug": "novosti-nauki",
        "title": "Новости науки",
        "content": "…",
        "terms": { … }
    },
    "locale": "ru"
}
```

404, если не найдено или status ≠ published.

## Автоматическая фильтрация драфтов

Если у модели в `$fillable` есть `status` — запросы автоматически
фильтруются по `status='published'`. Если `is_published` — по `true`.

Это значит: admin-только контент (черновики, архивные) **не утекает**
через API.

## Локализация

Пакет пытается локализовать каждое поле ресурса, перечисленное в
`'translatable'` его конфига:

```php
AdminCore::resource('articles', [
    'translatable' => ['title', 'excerpt', 'content'],
    // …
]);
```

Тогда в JSON-ответе `title`, `excerpt`, `content` уже содержат строку
нужной локали (не `{ru, kk, en}`), проходя через `Translatable::translate()`.

## Таксономии в response

Если модель `use Taxable` — в каждую запись добавляется ключ `terms`,
группирующий термины по type:

```json
{
    "terms": {
        "tag":      [{"slug": "…", "label": "…"}, …],
        "category": [{"slug": "…", "label": "…"}]
    }
}
```

Лейблы — в локали запроса.

## Authentication / rate limiting

API публичный **по умолчанию**. Если нужно:

- **Throttling** — в `routes/web.php` своего приложения переопредели
  group:

  ```php
  Route::middleware(['throttle:api'])->group(function () {
      Route::get('/api/content/{resource}', [...]);  // ← здесь уже ограничено
  });
  ```

- **Auth via Sanctum** — оберни роуты `auth:sanctum` middleware. Не
  забудь, что тогда SPA должен слать `Authorization: Bearer …`.

Это делается в consumer-приложении, т.к. пакет не форсирует.

## CORS

По умолчанию — Laravel-ный CORS (`config/cors.php`). Для публичного API
часто открывают:

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['*'],
'allowed_origins' => ['*'],
```

Для секьюрности — whitelist origins:

```php
'allowed_origins' => [
    'https://etec.edu.kz',
    'https://www.etec.edu.kz',
    'https://mobile-app.etec.kz',
],
```

## Кэширование

API не кэшируется на уровне пакета. Для публичного сайта рекомендуется:

- **HTTP-level кэш** через `Cache-Control` и CDN (Cloudflare).
- **Application кэш** в consumer-приложении через middleware.

Пример простого middleware:

```php
Route::get('/api/content/{resource}', function (Request $r, string $resource) {
    $key = 'content-api:' . $resource . ':' . http_build_query($r->query());
    return Cache::remember($key, 300, function () use ($r, $resource) {
        return app(\Meta\AdminCore\Http\Controllers\ContentApiController::class)
            ->resourceList($r, $resource);
    });
});
```

Инвалидация через `Webhookable` → `Cache::forget()` в job'е.

## Структура ответа — версионирование

На будущее: если нужно менять структуру, добавляй `X-API-Version` header
или prefix `/api/content/v2/…`. Сейчас API считается v1 (неявно).

## Следующее

→ [15. Sitemap.xml](./15-sitemap.md)
