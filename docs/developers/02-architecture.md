# 02. Архитектура

Пакет `meta/admin-core` — **headless-админка** для Laravel-сайтов. Ядро
трёх контуров:

1. **Backend** — Laravel-контроллеры, миграции, команды, Eloquent-модели.
2. **Frontend** — Inertia-страницы + Vue 3 SPA, использует Tiptap как
   rich-text редактор.
3. **Реестр** — `AdminCore` singleton: здесь регистрируются ресурсы,
   пункты меню, сайдбар-виджеты, schedulable-модели.

## Слои

```
┌─────────────────────────────────────────────────────────────┐
│  Consumer app (Laravel)                                     │
│    ├─ App\Models\{Article, Program, …}                     │
│    ├─ App\Providers\AdminResourceServiceProvider::boot()    │
│    │     ↓ AdminCore::resource(…)                           │
│    ├─ App\Support\EtecBlockCatalog (optional)               │
│    └─ bootstrap/providers.php                               │
├─────────────────────────────────────────────────────────────┤
│  meta/admin-core                                            │
│                                                             │
│    AdminCore (facade/singleton)                             │
│    ├─ resources[]                                           │
│    ├─ menuItems[]                                           │
│    ├─ dashboardStats[] / QuickActions[] / Recent[]          │
│    └─ schedulableModels[]                                   │
│                                                             │
│    AdminCoreServiceProvider                                 │
│    ├─ bind(BlockCatalog → DefaultBlockCatalog)              │
│    ├─ loadMigrationsFrom(…)                                 │
│    ├─ $this->app->booted(function () { … })                 │
│    │     ├─ registerFeatures()                              │
│    │     ├─ loadRoutesFrom('routes/admin.php')              │
│    │     ├─ add system menu items                           │
│    │     └─ dynamic per-resource routes                     │
│    └─ commands(InstallCommand, ApplyScheduleCommand, …)     │
│                                                             │
│    HTTP                                                     │
│    ├─ ResourceController        (generic CRUD)              │
│    ├─ PageBlockController       (блоки страниц)             │
│    ├─ MenuController            (меню)                      │
│    ├─ MediaController           (медиатека)                 │
│    ├─ FormsController           (формы)                     │
│    ├─ RevisionController        (ревизии)                   │
│    └─ …                                                     │
│                                                             │
│    Inertia SPA (Vue 3 + Tiptap)                            │
│    ├─ AdminLayout.vue           (сайдбар, шапка)             │
│    └─ pages/                                                │
│          ├─ Dashboard.vue                                   │
│          ├─ Resource/{Index,Form,Show}.vue                  │
│          ├─ Blocks/{Index,Form}.vue                         │
│          ├─ Revisions/Index.vue                             │
│          ├─ Permissions/Matrix.vue                          │
│          ├─ Taxonomies/Index.vue                            │
│          ├─ Forms/{Index,Edit,Submissions}.vue              │
│          └─ Webhooks/Index.vue                              │
├─────────────────────────────────────────────────────────────┤
│  PHP Extensions / Peer Libs                                 │
│    ├─ intervention/image-laravel  (optional — images+focal) │
│    ├─ spatie/laravel-permission   (optional — perms matrix) │
│    └─ ext-zip                     (optional — import/export)│
└─────────────────────────────────────────────────────────────┘
```

## Жизненный цикл запроса

Типичный HTTP-запрос к `/admin/articles`:

1. **Nginx/Plesk** → `public/index.php`.
2. **Laravel bootstrap** → `bootstrap/providers.php` → загрузка провайдеров:
   - `AppServiceProvider::register()` (твой).
   - `AdminCoreServiceProvider::register()` — binds `BlockCatalog`,
     singleton `AdminCore`.
   - `AdminResourceServiceProvider::register()` — может перебиндить
     `BlockCatalog` в `EtecBlockCatalog`.
3. **boot()** у всех провайдеров:
   - `AdminResourceServiceProvider::boot()` — **зовёт
     `AdminCore::resource(...)`** для каждой модели. Данные складываются
     в реестр.
   - `AdminCoreServiceProvider::boot()` — запускает `$app->booted(...)`
     замыкание, в котором:
     - Проходится по `AdminCore::getResources()` и регистрирует для
       каждого имени CRUD-маршруты в `/admin/{name}/…`.
     - Добавляет системные пункты меню.
4. **Routing** — Laravel резолвит `/admin/articles` → `ResourceController@index`.
5. **Controller** — берёт `AdminCore::getResource('articles')` →
   получает конфиг → делает `Article::query()->paginate()` → собирает
   данные по рецепту.
6. **Inertia::render('Resource/Index', $data)** — Laravel возвращает HTML
   + `<div id="app" data-page="…">`. Inertia-middleware складывает туда
   shared props (`auth.user`, `brand`, `navigation`).
7. **Vue** — монтируется, подтягивает `pages/Resource/Index.vue`,
   рендерит.

Следующие клики — уже через Inertia (AJAX без перезагрузки страницы).

## Контент vs. Админка

Публичная часть сайта (Blade-шаблоны в consumer-приложении) **читает** из
тех же Eloquent-моделей, которые пишет админка. Никакой магии: консьюмер
пишет обычный Blade с обычным Eloquent, использует хелпер `presented_block($block)`
чтобы удобно вытащить локализованные поля, и всё.

Подробнее про это → [08. Page Builder](./08-page-builder.md).

## Что в пакете, что в consumer-приложении

| Задача | Кто делает |
|---|---|
| Регистрация моделей в админке | **consumer** (AdminCore::resource) |
| Миграции базовых таблиц (page_blocks, menu_items, …) | пакет |
| Миграции полей бизнес-моделей (articles.slug, programs.tuition) | **consumer** |
| CRUD UI для ресурсов | пакет |
| Блоки страниц и визуальный конструктор | пакет |
| Публичные Blade-шаблоны сайта | **consumer** |
| Роуты публичной части | **consumer** |
| Tiptap редактор + медиатека | пакет |
| `/sitemap.xml` | пакет, URL'ы поставляет consumer через `AdminCore::sitemapUrl()` |
| `/api/content/*` | пакет (покрывает любой `AdminCore::resource()`) |

## Философия

1. **Реестр, а не код.** Чтобы добавить CRUD — не надо писать контроллер,
   форму, роут. Надо вызвать `AdminCore::resource()`. Все остальные
   шаги — пакет.
2. **Прозрачные Eloquent-модели.** Твоя модель `App\Models\Article` —
   обычный Eloquent. Пакет не оборачивает её в ничего.
3. **Blade для рендера.** Публичный сайт собирается в твоём consumer'е
   на обычном Blade. Vue — только в админке.
4. **Traits как расширение.** Хочешь ревизии? `use Revisionable` на
   модель. Хочешь автопубликацию? `use Publishable`. Хочешь теги? `use
   Taxable`. Ничего не «активируется» глобально — всё opt-in.
5. **Graceful degradation.** Отсутствующий spatie/permission не валит
   приложение — просто скрывает «Права доступа». Отсутствующий
   intervention/image — `ImageService::focalCrop()` возвращает
   оригинальный путь.

## Ключевые классы

- `Meta\AdminCore\AdminCore` — реестр.
- `Meta\AdminCore\Facades\AdminCore` — фасад (большинство примеров
  используют его).
- `Meta\AdminCore\Http\Controllers\ResourceController` — generic CRUD.
- `Meta\AdminCore\Concerns\*` — все traits (Translatable, Publishable,
  Revisionable, Taxable, Webhookable).
- `Meta\AdminCore\Contracts\BlockCatalog` — интерфейс расширяемого
  каталога страниц/типов блоков.
- `Meta\AdminCore\Support\DefaultBlockCatalog` — дефолтная реализация.
- `Meta\AdminCore\Services\ImageService` — загрузка + focal-crop.
- `Meta\AdminCore\Services\WebhookDispatcher` — отправка webhooks.
- `Meta\AdminCore\Content\PresentedBlock` + `BlockTypeRegistry` — DTO для
  рендеринга блоков в Blade.

Подробности — в остальных разделах документации.

## Следующее

→ [03. Конфигурация](./03-configuration.md)
