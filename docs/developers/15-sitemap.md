# 15. Sitemap.xml

Публичный XML-sitemap на `/sitemap.xml`. Consumer-приложение регистрирует
источники URL, пакет их собирает и кэширует.

## Регистрация источника

```php
use Meta\AdminCore\Facades\AdminCore;

AdminCore::sitemapUrl(fn () => [
    ['loc' => url('/'),        'priority' => '1.0', 'changefreq' => 'daily'],
    ['loc' => url('/about'),   'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => url('/programs'),'priority' => '0.9', 'changefreq' => 'weekly'],
]);
```

## Формат элемента

```php
[
    'loc'        => 'https://example.com/page',   // обязательный
    'lastmod'    => '2026-04-19T12:00:00+00:00',  // ISO 8601, опц.
    'changefreq' => 'weekly',                      // опц.
    'priority'   => '0.8',                         // опц. (0.0 – 1.0)
]
```

## Несколько источников

Каждый `AdminCore::sitemapUrl()` добавляет нового producer'а. Все
сливаются в один XML.

```php
// Статические URL
AdminCore::sitemapUrl(fn () => [
    ['loc' => url('/'),     'priority' => '1.0'],
    ['loc' => url('/about'),'priority' => '0.8'],
]);

// Из Page-модели
AdminCore::sitemapUrl(function () {
    return Page::where('status', 'published')
        ->get(['slug', 'updated_at'])
        ->map(fn ($p) => [
            'loc'     => url('/' . ltrim($p->slug, '/')),
            'lastmod' => optional($p->updated_at)->toIso8601String(),
        ])
        ->all();
});

// Из Article-модели
AdminCore::sitemapUrl(function () {
    return Article::published()
        ->get(['slug', 'updated_at'])
        ->map(fn ($a) => [
            'loc'        => url('/news/' . $a->slug),
            'lastmod'    => optional($a->updated_at)->toIso8601String(),
            'changefreq' => 'weekly',
            'priority'   => '0.7',
        ])
        ->all();
});
```

## Мульти-язычный sitemap

Если сайт многоязычный и есть URL вида `/en/about`, `/kk/about` — рендери
отдельные URL в цикле:

```php
AdminCore::sitemapUrl(function () {
    $pages = Page::where('status','published')->get();
    $locales = config('admin-core.locales', ['ru','kk','en']);

    $out = [];
    foreach ($pages as $p) {
        foreach ($locales as $l) {
            $prefix = $l === 'ru' ? '' : "/{$l}";
            $out[] = [
                'loc'     => url("{$prefix}/{$p->slug}"),
                'lastmod' => optional($p->updated_at)->toIso8601String(),
            ];
        }
    }
    return $out;
});
```

Для правильного `hreflang` нужен расширенный формат — не поддерживается
из коробки. Если нужен — допиши свой controller поверх пакета.

## Кэш

`/sitemap.xml` ответ кэшируется на `admin-core.sitemap.ttl` секунд
(по умолчанию 3600). Ключ кэша — `admin-core.sitemap.cache_key`.

Сбросить вручную:

```bash
php artisan cache:forget admin-core.sitemap.xml
# или
php artisan cache:clear
```

Инвалидировать на изменение контента:

```php
// В Article-модели:
protected static function booted(): void
{
    static::saved(function () {
        Cache::forget(config('admin-core.sitemap.cache_key'));
    });
}
```

Или через webhook:

```php
AdminCore::sitemapUrl($yourSource);

// в Webhookable-хуке:
$dispatcher = app(\Meta\AdminCore\Services\WebhookDispatcher::class);
\Cache::forget('admin-core.sitemap.xml');
```

## Выключить кэш

```env
ADMIN_SITEMAP_TTL=0
```

## Google Search Console

После деплоя зарегистрируй `https://your-site.com/sitemap.xml` в
Search Console. Обычно Google сам находит через `robots.txt`:

```
User-agent: *
Allow: /

Sitemap: https://your-site.com/sitemap.xml
```

## Следующее

→ [16. Forms API](./16-forms-api.md)
