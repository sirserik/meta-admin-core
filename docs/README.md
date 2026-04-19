# Документация `meta/admin-core`

Безголовая админ-панель (Laravel 11/12 + Inertia + Vue 3 + Tiptap). Ядро для
сайтов META University, ETEC и сторонних consumer-проектов.

Документация разделена на два раздела.

---

## 👤 [Для редакторов →](./users/README.md)

Инструкции для тех, кто наполняет сайт: создаёт страницы, редактирует блоки,
загружает медиа, настраивает формы. Ничего не требует знания кода.

Разделы:

1. [Вход и первое знакомство](./users/01-getting-started.md)
2. [Главный экран (Dashboard)](./users/02-dashboard.md)
3. [Страницы и блоки](./users/03-pages-and-blocks.md)
4. [Каталог типов блоков](./users/04-block-types.md)
5. [Меню сайта](./users/05-menu.md)
6. [Переводы (ru / kk / en)](./users/06-translations.md)
7. [Медиатека](./users/07-media.md)
8. [Формы и заявки](./users/08-forms.md)
9. [Словари (теги / категории)](./users/09-taxonomies.md)
10. [История изменений (Revisions)](./users/10-revisions.md)
11. [Запланированная публикация](./users/11-scheduled-publishing.md)
12. [Права доступа](./users/12-permissions.md)
13. [Webhooks — уведомления во внешние системы](./users/13-webhooks.md)
14. [Резервные копии](./users/14-backups.md)
15. [Сброс кэша](./users/15-cache.md)
16. [Общие настройки сайта](./users/16-site-settings.md)
17. [Тема и брендинг](./users/17-theme.md)
18. [Заявки и лиды](./users/18-leads.md)
19. [Журнал активности](./users/19-activity-log.md)

---

## 🧑‍💻 [Для разработчиков →](./developers/README.md)

Архитектура пакета, API расширений, примеры кода, развёртывание.

Разделы:

1. [Быстрый старт](./developers/01-quickstart.md)
2. [Архитектура](./developers/02-architecture.md)
3. [Конфигурация](./developers/03-configuration.md)
4. [Регистрация ресурсов — `AdminCore::resource()`](./developers/04-resources.md)
5. [Типы полей и атрибутов](./developers/05-fields.md)
6. [Условные поля (`visible_when`)](./developers/06-conditional-fields.md)
7. [Каталог блоков и DTO](./developers/07-block-catalog.md)
8. [Работа с `PageBlock`](./developers/08-page-builder.md)
9. [Мультиязычность — trait `Translatable`](./developers/09-translatable.md)
10. [Scheduled publishing — `Publishable`](./developers/10-publishable.md)
11. [Ревизии — `Revisionable`](./developers/11-revisionable.md)
12. [Таксономии — `Taxable`](./developers/12-taxable.md)
13. [Webhooks — `Webhookable`](./developers/13-webhookable.md)
14. [Read-only Content API](./developers/14-content-api.md)
15. [Sitemap.xml](./developers/15-sitemap.md)
16. [Program-level форм-билдер](./developers/16-forms-api.md)
17. [Интеграция с `spatie/laravel-permission`](./developers/17-permissions.md)
18. [Меню сайта](./developers/18-menu.md)
19. [Медиа и `ImageService::focalCrop`](./developers/19-media.md)
20. [Feature Modules](./developers/20-feature-modules.md)
21. [Темизация (design tokens)](./developers/21-theme.md)
22. [Autosave черновиков — `useDraftAutosave`](./developers/22-drafts.md)
23. [Live preview iframe](./developers/23-live-preview.md)
24. [Обновление пакета](./developers/24-upgrading.md)
25. [Миграции и полиморфные связи](./developers/25-migrations.md)
26. [Artisan-команды](./developers/26-artisan-commands.md)
27. [Расширение Vue-интерфейса](./developers/27-extending-admin-ui.md)
28. [Тестирование](./developers/28-testing.md)
29. [Траблшутинг](./developers/29-troubleshooting.md)

---

## Версии

Актуальный релиз: **v0.43.1** (апрель 2026).
Полный список изменений — [`CHANGELOG.md`](../CHANGELOG.md).

## Лицензия

Proprietary — META University. Без разрешения не использовать.
