# Документация для разработчиков

Техническая документация `meta/admin-core` — архитектура, API расширений,
примеры, развёртывание.

Пакет — **не полное CMS**, а **ядро админки**. Он не знает о твоих
моделях, формах, роутах. Ты регистрируешь ресурсы, пакет строит вокруг
них CRUD-экраны, сайдбар, переводы, ревизии, webhooks и прочее.

## Зависимости

- PHP 8.2+
- Laravel 11 или 12
- `inertiajs/inertia-laravel` 2.x / 3.x
- *(soft)* `spatie/laravel-permission` — нужна для матрицы прав
- *(soft)* `intervention/image-laravel` — для WebP-конвертации + focal-crop
- Frontend: Vue 3 + Inertia + Tiptap (собирается через Vite в consumer-приложении)

## Оглавление

### Основы

1. [Быстрый старт](./01-quickstart.md) — установка в новый Laravel-проект
2. [Архитектура](./02-architecture.md) — из чего состоит пакет
3. [Конфигурация](./03-configuration.md) — `config/admin-core.php`

### Ресурсы и формы

4. [`AdminCore::resource()` — регистрация](./04-resources.md)
5. [Типы полей и атрибутов](./05-fields.md)
6. [Условные поля (`visible_when`)](./06-conditional-fields.md)

### Page Builder

7. [`BlockCatalog` и DTO](./07-block-catalog.md)
8. [Работа с `PageBlock`](./08-page-builder.md)

### Traits (модельные миксины)

9. [`Translatable` — мультиязычность](./09-translatable.md)
10. [`Publishable` — scheduled publishing](./10-publishable.md)
11. [`Revisionable` — ревизии](./11-revisionable.md)
12. [`Taxable` — таксономии](./12-taxable.md)
13. [`Webhookable` — webhooks](./13-webhookable.md)

### Публичные API

14. [Content API (`/api/content/*`)](./14-content-api.md)
15. [Sitemap.xml](./15-sitemap.md)
16. [Forms API (`/api/forms/{slug}`)](./16-forms-api.md)

### Права и навигация

17. [Интеграция с `spatie/laravel-permission`](./17-permissions.md)
18. [Меню сайта](./18-menu.md)

### Медиа и модули

19. [`ImageService::focalCrop`](./19-media.md)
20. [Feature Modules](./20-feature-modules.md)
21. [Темизация (design tokens)](./21-theme.md)

### Фронтенд

22. [`useDraftAutosave` composable](./22-drafts.md)
23. [Live preview iframe](./23-live-preview.md)

### Операции

24. [Обновление пакета](./24-upgrading.md)
25. [Миграции и morph-типы](./25-migrations.md)
26. [Artisan-команды](./26-artisan-commands.md)
27. [Расширение Vue-интерфейса](./27-extending-admin-ui.md)
28. [Тестирование](./28-testing.md)
29. [Траблшутинг](./29-troubleshooting.md)
30. [Admin recovery (PIN-gated)](./30-admin-recovery.md) — off-band сброс пароля админа через PIN из `.env`
31. [`document-list` block type](./31-document-list.md) — канонический блок «список документов/ссылок» + `<x-admin-core::documents>` компонент

---

## Структура репозитория пакета

```
meta-admin-core/
├── config/
│   ├── admin-core.php          # prefix, middleware, brand, locales, features
│   └── theme.php               # design tokens
├── database/migrations/        # автозапускаемые через ServiceProvider
├── resources/js/
│   ├── components/             # переиспользуемые (TranslatableField, SimpleField, …)
│   ├── composables/            # useDraftAutosave, …
│   ├── layouts/AdminLayout.vue # главный макет
│   └── pages/                  # Inertia-страницы: Resource/, Blocks/, Forms/, …
├── routes/
│   ├── admin.php               # всё под /admin, middleware web+auth+verified
│   └── public.php              # /sitemap.xml, /api/content/*, /api/forms/{slug}, /media/*
├── src/
│   ├── AdminCore.php           # реестр ресурсов, меню, dashboard-виджетов
│   ├── AdminCoreServiceProvider.php
│   ├── Facades/AdminCore.php
│   ├── Concerns/               # Translatable, Publishable, Revisionable, Taxable, Webhookable
│   ├── Console/Commands/       # artisan admin-core:*
│   ├── Content/                # PresentedBlock + DTO
│   ├── Contracts/BlockCatalog.php
│   ├── Events/                 # LeadCreated, SettingUpdated
│   ├── Features/               # FeatureModule базовый класс
│   ├── Http/Controllers/       # ResourceController, PageBlockController, …
│   ├── Models/                 # PageBlock, MenuItem, Revision, Webhook, TaxonomyTerm, …
│   ├── Services/               # ImageService, CacheService, WebhookDispatcher
│   └── Support/                # DefaultBlockCatalog, SitemapRegistry, PublishableSchema
└── CHANGELOG.md
```

## Принципы

1. **Декларативно, а не императивно.** Ресурс = массив опций, не CRUD-класс.
2. **Graceful degradation.** Пакет не должен ронять boot-цикл Laravel.
3. **Традиционный Laravel внутри.** Никаких custom DSL — только Eloquent,
   Inertia, Blade для публичной части.
4. **Расширяемость через hooks.** `enabledPageSlugs()`, `sitemapUrl()`,
   `schedulable()` — всё через публичные API.
5. **Back-compat.** Мажор-версии **0.x** могут ломать, после **1.0.0** —
   semver strict.

---

## Версия

Актуально для `meta/admin-core` **v0.43.1** и выше.
