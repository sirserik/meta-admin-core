# Changelog

All notable changes to `meta/admin-core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.15.0] — 2026-08-08

### Added
- **Повторяющиеся группы в формах ресурсов** (`RepeaterField`). Раньше списки
  (команда, авторы, ссылки, материалы) приходилось складывать построчным
  текстом в textarea и разбирать на бэкенде: редактор блоков умел `type: 'array'`
  с `item_fields` давно, а формы ресурсов — нет. Теперь описание то же самое:
  `['name' => 'team', 'type' => 'array', 'item_fields' => [['key' => 'name',
  'label' => 'ФИО', 'type' => 'text'], ['key' => 'photo', 'type' => 'image']]]`.
  Строки добавляются, удаляются и двигаются вверх-вниз; значение — массив
  объектов, поэтому колонка модели должна быть json (или иметь `array` в `$casts`).
  Работает и в основной колонке формы, и в сайдбаре.
- **Типы полей `image` и `file` у `SimpleField`.** `type => 'image'` в ресурсе
  молча уходил в ветку «обычный input», и браузер рисовал `<input type="image">` —
  кнопку-картинку вместо поля. Теперь это путь + кнопка загрузки + превью,
  через те же `/admin/upload/image` и `/admin/upload/file`, что и в блоках.
- `Meta\AdminCore\Support\ParsesLineLists` — разбор построчных списков
  «значение | значение» для данных, которые уже накоплены в текстовых колонках.
  Для новых ресурсов предпочтителен `type => 'array'`.

### Fixed
- **`textarea` в форме ресурса обрезался на 500 знаках.** Лимит по умолчанию
  был общий с однострочным полем, поэтому длинный список сохранить было нельзя —
  форма возвращала ошибку валидации. Теперь 20 000 знаков, явный `max` уважается.
- Валидация `type => 'array'` больше не проверяет массив как строку.

## [1.12.0] — 2026-07-16

### Added
- **Поворот картинок в админке — везде, где есть картинка.** Повод: на сайте
  всплыло фото, физически повёрнутое на 90° — исправлять пришлось руками.
  - `ImageService::rotate(path, degreesCw)` — поворот НА МЕСТЕ (тот же путь,
    тот же формат), поэтому все ссылки на файл — JSON `page_blocks`, колонки
    моделей, rich-text-контент — остаются валидными. Intervention v3, при его
    отсутствии GD-фоллбек (jpg/png/gif/webp; альфа сохраняется). SVG и
    path-traversal отклоняются.
  - `POST /admin/upload/rotate-image` `{path|url, degrees}` — универсальный
    JSON-эндпоинт; принимает и полный URL, и путь с `/storage|/media`-префиксом;
    синхронизирует width/height/size связанной записи `Media`.
  - `POST /admin/media/{id}/rotate` — поворот из медиа-библиотеки (Inertia),
    обновляет метаданные записи.
  - UI-кнопки «повернуть на 90°»: карточки **медиа-библиотеки** (hover),
    сайдбар картинки **Resource/Form** (уже сохранённая — через сервер;
    только что выбранный файл — canvas-поворот ДО загрузки),
    **BlockDataEditor** — превью+кнопка у `image`-полей верхнего уровня и в
    `item_fields`. Превью обновляются cache-buster'ом, значение поля не меняется.
  - EXIF-ориентация при загрузке выправляется автоматически там, где стоит
    Intervention v3 (авто-orient на декоде) — отдельного кода не потребовалось.

## [1.11.0] — 2026-07-14

### Fixed
- **`BlockDataEditor` — редактор переводимых полей ВЕРХНЕГО уровня.** Раньше
  виджеты `translatable` / `translatable_textarea` / `translatable_file`
  работали только для под-полей внутри массивов (`item_fields`); те же типы
  на верхнем уровне схемы (напр. `badge`, `heading` у `documents-i18n`)
  попадали в обычный `<input>` и показывали `[object Object]` — а сохранение
  превращало `{ru,kk,en}`-карту в строку одной локали. Добавлены top-level
  ветки для всех трёх типов + хелпер `setTField(key, locale, value)`
  (по образцу `setTRow`). Локаль-пикер уже учитывал верхний уровень. Теперь
  бейдж/заголовок секции редактируются по языкам как обычные карточки.

## [1.10.0] — 2026-06-02

### Added
- **`admin-core:media-check`** + сервис **`Services\MediaIntegrity`** — находит «тихо битые»
  медиа: файлы с расширением картинки/PDF, внутри которых на самом деле HTML (артефакт
  краул/миграции — отдаются `200` с image-MIME по расширению, `curl` выглядит ок, но браузер
  не рендерит). Проверка по СОДЕРЖИМОМУ, не по HTTP-статусу. Флаги: `--disk` (дефолт public),
  `--dir` (подкаталог), `--from=<зеркало>` + `--fix` (починить из валидного зеркала, напр.
  `public/media`), `--limit`. `MediaIntegrity::isCorrupt(ext, head)` — чистая, тестируемая
  (svg/xml не флагаются; учитывает BOM). Дополняет семейство `admin-core:storage-*` из v1.6.

## [1.9.0] — 2026-06-02

### Added
- **Document attachments** — полиморфная таблица `documents` + модель
  `Meta\AdminCore\Models\Document` + трейт `Concerns\HasDocuments`
  (`$model->documents`), заменяет per-parent копии (ArticleDocument/
  NewsDocument). `DocumentController`: admin CRUD (`{prefix}/documents`) +
  публичные download/view. **Файлы всегда отдаются как attachment** с nosniff
  + `default-src 'none'` CSP (защита от payload в толерантных mime). Загрузка
  ограничена `getSupportedFileTypes()` + allowlist `documents.attachable`.
  Анонимный доступ гейтится контрактом `Contracts\PubliclyVisible` (файлы
  неопубликованного родителя не утекают). Конфиг — `admin-core.documents`.
- **`admin-core:revisions-prune`** — оставляет последние N ревизий на сущность
  (Revisions фича копила вечно). `--keep`, `--dry-run`. DB-агностично.
- **`admin-core:make-admin`** — создать/повысить админа из CLI (модель из
  `auth.providers.users.model`, опц. роль spatie из `admin_roles`).
- Документация — `docs/documents.md`.

## [1.8.0] — 2026-06-02

### Added
- **Editor hygiene** — чистка мусора rich-editor полей:
  - **`Meta\AdminCore\Services\EditorHygiene`** — чистые трансформы
    `cleanGoogleDocs()` / `paragraphsToLists()` / `extractBase64()`
    (переиспользуемы на save; extractBase64 принимает `$persist`-callback —
    storage-агностичен).
  - Команды `admin-core:content-cleanup-gdocs` / `content-paragraphs-to-lists`
    / `content-extract-base64` (все с `--dry-run`, `--target`). Свипят
    таблицы из `admin-core.editor_hygiene.targets` (table⇒columns), отсутствующие
    таблицы/колонки пропускаются. DB-driver-агностично (query builder, chunked).
  - Конфиг-блок `admin-core.editor_hygiene`. Документация — `docs/editor-hygiene.md`.

## [1.7.0] — 2026-06-02

### Added
- **Security & runtime middleware** (aliases; two auto-attachable to `web`):
  - `admin-core.security-headers` (`SecurityHeaders`) — nosniff / X-Frame-Options /
    Referrer-Policy / Permissions-Policy всегда; опц. HSTS; CSP из конфига
    (`security.csp.directives`, Report-Only→enforce), пропускается на admin-префиксе.
    Авто-подключение в `web` через `ADMIN_CORE_SECURITY_HEADERS=true`.
  - `admin-core.honeypot` (`HoneypotProtection`) — скрытое поле + min-time anti-spam
    для публичных форм (имя поля/время настраиваются).
  - `admin-core.admin` (`EnsureUserIsAdmin`) — гейт админки: Gate `admin-core.access-admin`
    → spatie `hasAnyRole(admin_roles)` → любой залогиненный (spatie опционален).
  - `admin-core.redirects` (`HandleRedirects`) — 301/302 из таблицы `redirects` на GET,
    кэш 5 мин, инкремент `hits`. Авто-подключение через `ADMIN_CORE_REDIRECTS_RUNTIME=true`.
- **`Meta\AdminCore\Models\Redirect`** + миграция, добавляющая колонку `hits` в
  `redirects` (guarded — no-op если уже есть/таблицы нет).
- Конфиг-блок `admin-core.security` + `admin_roles`. Документация — `docs/security-middleware.md`.

## [1.6.0] — 2026-06-02

### Added
- **Server Ops** — серверное самообслуживание из админки с изоляцией
  привилегий (PHP пишет запрос, root-cron исполняет, root не запускает PHP):
  - **`BackupFeature`** (opt-in `FEATURE_BACKUP`) — создание/скачивание/
    восстановление бэкапов БД и файлов на `/{prefix}/backups`. Контроллер
    кладёт JSON-запрос в spool; root-агент (`admin-core:backup-agent-script`)
    делает дампы/restore и пишет `status.json`. Restore всегда снимает
    защитную `pre-restore-*` копию. **DB-агностично** (pgsql/mysql/sqlite).
    Заменяет на self-hosted-нодах старый in-process `/{prefix}/backup`
    (SQLite-zip остаётся для Plesk/managed). Конфиг — `admin-core.backup`.
  - **Step-up ops-PIN gate** — middleware-алиас `admin-core.ops-pin` +
    `OpsPinController` + страница `/{prefix}/unlock`. Второй фактор поверх
    admin-авторизации для firewall/backups; no-op без `ADMIN_OPS_PIN`.
    Firewall-маршруты теперь по умолчанию проходят через этот гейт.
  - **Storage-команды** — `admin-core:storage-check` / `storage-relink` /
    `storage-fix-permissions` / `storage-cleanup-backup` (лечат 403/битые
    медиа и неправильный `public/storage`).
- Документация — `docs/server-ops.md`.

## [1.5.0] — 2026-06-02

### Added
- **`FirewallFeature`** (opt-in, `FEATURE_FIREWALL=true`) — управление списком
  IP для входа по SSH (порт 22) прямо из админки, без выдачи веб-процессу
  никаких привилегий. Ключевая ценность — изоляция привилегий:
  - админ-страница ТОЛЬКО пишет строки в таблицу `firewall_rules`;
  - применяет ufw отдельный **root-cron скрипт** (генерится командой
    `php artisan admin-core:firewall-sync-script`) — root никогда не
    исполняет PHP приложения (минимальная поверхность атаки);
  - `emergency_ip` вшит в скрипт → список не может опустеть, лок-аут
    невозможен.
  - Self-contained Blade-страница `/{prefix}/firewall` (намеренно НЕ SPA —
    это break-glass инструмент, должен работать даже при сломанном
    SPA-билде). 404-ит, когда фича выключена.
  - Скрипт синхронизации DB-агностичен: pgsql / mysql / sqlite (годится и
    для Postgres-серверов, и для SQLite-сайтов типа ETEC).
  - Опциональный step-up gate через `admin-core.firewall.gate` (например
    ops-PIN middleware).
  - Состав: `Models\FirewallRule` (+ валидатор IPv4/CIDR), миграция
    `firewall_rules`, `Http\Controllers\FirewallController`, вьюха
    `admin-core::firewall`, `routes/firewall.php`, команда
    `admin-core:firewall-sync-script`. Конфиг — блок `admin-core.firewall`.
  - Установка root-скрипта — единственный ручной (привилегированный) шаг,
    пакет его не делает из PHP. См. `docs/firewall.md`.

## [1.4.0] — 2026-05-21

### Added
- **`Meta\AdminCore\Support\LegacyBlocksCatalog`** — каталог из 34 общих
  legacy-блоков для sister-сайтов на базе ETU/ETEC. Включает:
  - 11 home-блоков (stat, metric, ui_element, heading, feature_card,
    feature_item, news_card, program_card, admission_step, form_option,
    programs)
  - 8 green-deal-center секций (content-section, features-section,
    news-section, research-section, publications-section, contacts-section,
    rich-content, programs-table)
  - 7 infrastructure-блоков (buildings, labs, gallery_slider,
    it_infrastructure, sports, dormitories, virtual_tour)
  - 7 misc-блоков (legal-document, achievements, faculty, contact, form,
    settings, section-header)
  - 1 menu_item (translatable header label)
  - Расширенные schemas для admin-core `content` (15 типов sub-shape:
    cards/features/items/programs/categories) и `partners-section`
    (категория на каждом партнёре).
- Consumer-сайты делают `extends LegacyBlocksCatalog` и переопределяют
  только site-specific блоки (ETU: grid-cards с nested links;
  ETEC: 21 flat-i18n тип).

### Migration
Существующие сайты с самописными `EtuBlockCatalog`-style классами
переключаются одной строкой:
```php
// app/Support/EtuBlockCatalog.php
- class EtuBlockCatalog extends DefaultBlockCatalog
+ class EtuBlockCatalog extends \Meta\AdminCore\Support\LegacyBlocksCatalog
// удалить ETU_LEGACY_BLOCK_TYPES const + дублирующие методы schemas
```

## [1.3.2] — 2026-05-21

### Fixed
- **`/storage/storage/...` and `/media/storage/...` paths now resolve.**
  Some upload handlers (TipTap admin editor seen in ETU production) sometimes
  store an URL like `/storage/storage/news/X.jpg` — admin-core's media
  serve was redirecting that verbatim to `/media/storage/news/X.jpg`
  which then 404'd. Now `storage/` prefix is stripped (any number of
  repeats) before the file lookup, so old broken URLs serve the file
  anyway and the visitor sees the picture.

## [1.3.1] — 2026-05-21

### Security
- **Dropped `svg` from `/admin/upload/image` allowed mimes.** Inline SVG
  can carry `<script>` blocks executing under admin-session origin.
  The endpoint is also reachable from the public rich-text editor, so
  any logged-in editor surfaced the risk. Sites that genuinely need
  SVG uploads should override `UploadController::uploadImage` with
  their own controller and sanitize first.

## [1.3.0] — 2026-05-21

### Fixed
- **Morph-type drift** между admin-core моделями и consumer-моделями
  закрыт окончательно. Это проявлялось, когда сайт держал свою
  `App\Models\PageBlock` рядом с пакетной `Meta\AdminCore\Models\PageBlock`:
  каждая писала в `translations.translatable_type` свой FQCN, и public-
  side читал старые значения, не видя свежие admin-side изменения
  (kk/en title после редактирования).

### Added
- **`getMorphClass()` override** в моделях PageBlock, MenuItem, Setting, Lead.
  Возвращает стабильный alias (`'page_block'`, `'menu_item'`, `'setting'`,
  `'lead'`) — consumer-модели на тот же ряд DB получают тот же alias
  и translations table консолидируется.
- **AdminCoreServiceProvider регистрирует default morphMap** на эти
  aliases. Consumer override через свой `Relation::morphMap([...])` в
  AppServiceProvider — выигрывает (Laravel array_merge порядок).
- **`Concerns/Translatable::saveTranslations()`** теперь использует
  `$this->getMorphClass()` вместо `get_class($this)` — записи идут с
  alias, не FQCN.
- **`php artisan core:migrate-morph-types`** — миграция существующих
  morph-rows с FQCN на aliases. Покрывает translations, revisions,
  activity_logs, media. Dedup при unique-constraint collision
  (предпочитает alias-row, удаляет FQCN-дубль). `--apply` для
  фактического update, без флага — dry-run.

### Migration guide
Для существующих сайтов:
```bash
php artisan core:migrate-morph-types         # dry-run — посмотреть что будет
php artisan core:migrate-morph-types --apply # выполнить
```
В consumer AppServiceProvider можно оставить custom morphMap если хочется
переопределить admin-core defaults — он выиграет:
```php
Relation::morphMap([
    'page_block' => \App\Models\PageBlock::class, // consumer wins
]);
```

### Verified in regression (etu-new)
End-to-end: admin PUT блока через Inertia → переводы RU/KK/EN записываются
с alias `'page_block'` → public-side `App\Models\PageBlock::translate()`
читает их корректно во всех трёх локалях. До патча — public видел только
RU из scalar column'а, kk/en терялись.

## [1.2.0] — 2026-05-21

### Fixed
- **Sitemap (и другие public routes) теперь не конфликтуют с consumer'ом.**
  Package routes из `routes/public.php` (`/media/*`, `/sitemap.xml`,
  `/api/forms/*`, `/api/content/*`) загружаются в `booted()` колбэке после
  consumer'а `routes/web.php`, и в Laravel последний registered route с
  тем же URI выигрывает. Это значило: если у consumer'а уже есть
  `/sitemap.xml` (как у ETU), его перекрывал package'овский, что давало
  пустой XML.

### Added
- **`config('admin-core.routes')`** — opt-out для каждого публичного роута:
  `media`, `sitemap`, `forms`, `content_api`. Все default = `true`,
  переопределяются через env (`ADMIN_CORE_ROUTE_MEDIA=false` и т.д.).
- **Auto-skip по URI** — если на момент регистрации package-роутов уже
  существует consumer-роут с тем же URI и методом, пакет тихо пропускает
  свой. Safety net: даже если забыли выключить через config, conflict
  не возникает.

### Migration
Никаких действий не требуется. Существующие consumer'ы продолжают работать.
Если у тебя был ETU-style собственный `/sitemap.xml` — он теперь побеждает
автоматически.

## [1.1.0] — 2026-05-21

### Added
- **Block variants** — `BlockDefinition::variants(): array` декларирует
  альтернативные вёрстки одного и того же блока (аналог Gutenberg block
  patterns). Админка показывает выбор, value лежит в `data['variant']`,
  рендер автоматически диспатчит на `blocks.v2.{handle}.{variant}.blade.php`
  с fallback на базовый `blocks.v2.{handle}.blade.php`.
- **`BlockDefinition::renderVariant($data, $locale, $extra)`** — helper для
  реализаций `render()`, который находит правильный view и передаёт
  payload. Заменяет ручной `View::make('blocks.v2.foo', [...])`.
- **Hero block — 3 variants**: `default`, `centered`, `split`. Поставляются
  как `resources/views/blocks/v2/hero/{centered,split}.blade.php`.
- **Stats block — 2 variants**: `default` (сетка с цветным фоном),
  `inline` (горизонтальная строка без фона).
- **`php artisan core:make-block {Name}`** — скаффолдинг кастомного блока:
  создаёт `app/Blocks/{Name}Block.php` (extends `BlockDefinition`) и
  `resources/views/blocks/v2/{handle}.blade.php` из stubs, печатает
  готовую строку регистрации `AdminCore::registerBlock(new {Name}Block)`.
  Поддерживает `--handle=`, `--label=`, `--force`.
- **stubs/block.stub + block-view.stub** — шаблоны для команды выше,
  включают комментарий «как раскомментировать variants()».

### Notes
- Старые `View::make('blocks.v2.hero', [...])` остаются рабочими — variant
  system additive, не ломающее изменение. Можно мигрировать на
  `renderVariant()` блок за блоком.

## [1.0.0] — 2026-05-20

### Added
- **Block library в core**: 18 встроенных блоков перенесены из consumer-сайтов
  (ETU `app/Blocks/Definitions/*`) в пакет под `Meta\AdminCore\Blocks\Definitions\*`.
  Hero, Content, Cta, Features, Stats, Gallery, Links, Heading, Faq,
  AdmissionStep, Programs, Team, Timeline, PartnersSection, Video, SingleImage,
  DocumentGroup, DescriptionTable. Каждый — `BlockDefinition` со schema-первой
  моделью (handle/label/category/icon/schema/render).
- **`AdminCore::useBlocks($handles)`** — opt-in регистрация встроенных блоков
  из AppServiceProvider консумера. `useBlocks('*')` подключает все. Передача
  неизвестного handle бросает `InvalidArgumentException` на boot (typo
  surface).
- **`AdminCore::registerBlock($block)`** — регистрация кастомного site-specific
  блока (extends `BlockDefinition`).
- **`Meta\AdminCore\Blocks\BlockRegistry`** — singleton, реестр handle→Definition,
  методы `register()`, `get()`, `byCategory()`, `render()`, `validate()`.
- **View-path авто-загрузка**: `resources/views/blocks/v2/{handle}.blade.php`
  из пакета добавляется как low-priority view location. Сайт может
  переопределить любой блок, положив свой шаблон в `resources/views/blocks/v2/`
  — Laravel view finder подхватит локальную копию первой. `vendor:publish`
  не нужен.

### Removed (BREAKING)
- Consumer-сайты больше не должны держать локальный `app/Blocks/Definitions/*`
  для встроенных блоков. Удалите дубликаты и подключите `AdminCore::useBlocks('*')`
  в `AppServiceProvider::boot()`. Кастомные блоки сайта остаются в `app/Blocks/`
  и регистрируются через `AdminCore::registerBlock()`.
- `\App\Blocks\BlockRegistry` → `\Meta\AdminCore\Blocks\BlockRegistry`. Все
  ссылки в Blade-компонентах (`page-blocks.blade.php`, `block-renderer.blade.php`)
  должны быть обновлены.

### Migration guide

В consumer-сайте:
```diff
- App\Providers\BlockServiceProvider::class,  // bootstrap/providers.php
```
```diff
- $registry = $this->app->make(BlockRegistry::class);
- $registry->register(new HeroBlock); … (×18)   // BlockServiceProvider::boot
+ AdminCore::useBlocks('*');                    // AppServiceProvider::boot
```
```diff
- rm -rf app/Blocks/
- rm app/Providers/BlockServiceProvider.php
```

## [0.56.0] — 2026-04-29

### Added
- **`auto_publish_at` resource option** — когда `true`, `ResourceController`
  и при создании, и при редактировании ставит `published_at = now()` в
  момент, когда `status` переключается в `'published'`, а `published_at`
  пуст. Имена колонок фиксированы (`status`, `published_at`); если хотя
  бы одной нет на таблице — опция тихо игнорируется.
  ```php
  AdminCore::resource('news', [
      'model'           => News::class,
      'auto_publish_at' => true,
      // …
  ]);
  ```

### Why
Публичные scope-ы вида `News::published()` фильтруют по
`status='published' AND published_at <= now()`. Если редактор отметил
«Опубликовано», но забыл выставить дату — запись сохранялась, но на
сайт не попадала, потому что `published_at IS NULL`. Раньше каждый
сайт-потребитель городил свой `saving`-хук на модель — теперь общий.

## [0.55.0] — 2026-04-29

### Added
- **`author_field` resource option** — имя колонки автора
  (`author_id`, `user_id`, `created_by`…). При создании ресурса через
  админку `ResourceController::store()` автоматически подставит
  `Auth::id()` в эту колонку, если форма её не передаёт. Колонка должна
  существовать на таблице модели; иначе игнорируется.
  ```php
  AdminCore::resource('news', [
      'model'        => News::class,
      'author_field' => 'author_id',
      // …
  ]);
  ```
- **`default` per-attribute option** — для любого `attributes[]`-поля
  можно указать дефолтное значение. Применяется при создании ресурса,
  если форма прислала пустую строку или не прислала ключ вовсе.
  ```php
  ['name' => 'status', 'type' => 'select',
   'options' => $statusOptions, 'default' => 'draft'],
  ```

### Why
Без этих опций ресурсы вроде `news`/`pages` с NOT NULL колонками
(`status`, `author_id`) падали в админке на SQLSTATE 23000 при создании,
если форма поля не передавала. Раньше каждый сайт-потребитель городил
свой `creating`-хук на модель — теперь логика общая.

## [0.54.0] — 2026-04-28

### Added
- **`ProcurementsFeature`** — opt-in module that ships a procurements
  / tenders directory out of the box. Toggleable from `/admin/features`
  like other Feature modules (Green Deal, SDG).
  - `Meta\AdminCore\Models\Procurement` — full model: HasSlug,
    Translatable (title/summary/customer), `HasContentBlocks` for
    polymorphic block stack per row, scopes
    (published/byStatus/byType/byYear/search), display helpers
    (statusLabel/typeLabel/statusColor, isDeadlineSoon).
  - Migration `2026_04_28_000002_create_procurements_table` —
    idempotent (skipped on consumers that already created the table
    locally; ETU's pre-existing `2026_04_27_120100` migration
    coexists fine).
  - The feature self-registers `AdminCore::resource('procurements', …)`
    with a generic Resource/{Index,Form}.vue (translatable ru/kk/en
    tabs, status / procurement_type filters, all metadata fields),
    plus `AdminCore::previewResolver('/^procurement-(\d+)$/', …)`
    that maps synthetic page_names to the consumer's
    `/procurements/{slug}` URL.
  - Resource's `edit_url` jumps from the row to its block-builder
    (`/admin/blocks?page=procurement-{id}`) so editors land in the
    polymorphic block stack right after saving meta-fields.

### Consumer responsibilities

The package owns the model + admin form. Each consumer site adds:
- routes — `/procurements` and `/procurements/{slug}`,
- public Blade views (listing + detail with `<x-page-blocks>`),
- frontend translations,
- nav/menu entry pointing at `/procurements`,
- optional `PageBlock::saving` observer to back-fill `blockable_*`
  from the synthetic page_name written by the existing block-editor
  (one preg_match + class_exists guard).

ETU (consumer #2) is the reference implementation — see its
`procurements/` views and the AppServiceProvider observer.

## [0.53.0] — 2026-04-28

### Added
- **Polymorphic `page_blocks`.** Any Eloquent model can now own a
  flexible stack of blocks via `blockable_type/blockable_id`, in
  addition to the legacy `page_name`-keyed binding. Useful for
  per-row content like procurements, programs, news — anything where
  each row deserves its own block stack instead of a single named
  page.
  - Migration `2026_04_28_000001_add_blockable_to_page_blocks` adds
    the two columns + a `(blockable_type, blockable_id, is_active,
    sort_order)` index. Idempotent — guarded by `Schema::hasColumn`,
    so consumers that already added these columns locally won't
    conflict.
  - `PageBlock::blockable()` morphTo, `scopeForBlockable($type, $id)`,
    `getBlocksFor($owner, $publishedOnly)` static factory with cache
    keyed by `blockable_<type>_<id>_<pub|all>` (matches the
    `booted()` invalidation).
  - New `Meta\AdminCore\Concerns\HasContentBlocks` trait for owner
    models — gives `contentBlocks` MorphMany, `loadContentBlocks()`
    helper, `contentBlocksPageName()` for synthetic page_name
    derivation (e.g. `procurement-{id}` for the legacy block editor
    URL).
  - Docs: `docs/POLYMORPHIC-BLOCKS.md` walks through owner-side
    usage, block creation, live-preview registration, and rendering.

### Backwards compatibility

Existing `page_name`-only blocks are unchanged. New polymorphic
rows can also carry a `page_name` (the synthetic key) so the
existing `<x-page-blocks>` machinery and `/admin/blocks?page=…`
editor URL continue to work without changes.

## [0.52.0] — 2026-04-28

### Added
- **Preview-URL resolver hook.** `AdminCore::previewResolver(string $regex, callable $fn)`
  registers a function that maps a synthetic block `page_name` (e.g.
  `procurement-{id}`, `program-{id}` — anything attached via polymorphic
  `blockable`) to the public URL the live-preview iframe should load.
  Without it the iframe always built `/{page_name}` and 404'd for
  synthetic keys; consumer sites worked around it with route-redirects.
  `AdminCore::resolvePreviewUrl(string $pageName)` returns the first
  non-null match. `Form.vue` falls back to the default `/{page_name}`
  when no resolver matches, so existing sites are unaffected.
- **Three universal block types** registered out-of-the-box, with admin
  schemas and visual editors via `BlockDataEditor`:
  - `description_table` — «таблица описаний»: rows of «описание / дата
    / переключаемая по локали ссылка-файл / тип ссылки», с № и зеброй.
    Универсально под любые реестры документов.
  - `document_group` — PDFs/DOCs grouped by category или year, layouts
    tabs / accordion / list. Каждый файл — translatable title, file,
    description, category.
  - `single_image` — крупное изображение (для сканов A4 и фасадов) с
    zoom по клику, выбором пропорций (auto/4:3/16:9/A4), ширины
    (container/wide/full) и фона.

### Changed
- **`clubs-grid` schema added.** Previously `clubs-grid` was listed in
  `BLOCK_TYPES` but had no admin schema → editors saw a raw JSON
  textarea. Now ships per-card fields: logo (image-upload), icon
  (text fallback), title, description, url. Same shape ETU was
  carrying as a local override — moved into the package so etec /
  other consumers get it automatically.

### Migration
- Sites that registered any of these schemas locally (e.g. ETU's
  `EtuBlockCatalog::descriptionTableSchema()` / `documentGroupSchema()`
  / `singleImageSchema()` / `clubsGridSchema()`) can drop those
  overrides — the package's schemas are equivalent and pick up
  through `parent::blockSchema()`.

## [0.51.5] — 2026-04-28

### Fixed
- **Block form: page select preserves synthetic page_name on edit.**
  When a block lives on a page that isn't listed in `BlockCatalog::PAGES`
  (e.g. polymorphic `procurement-{id}` rows that ETU's Procurement
  module attaches via `page_blocks.blockable_type/_id`), the page-select
  found no matching `<option>` and silently reset to "" — pressing
  «Сохранить» without touching anything would then fail validation
  with «выбери страницу». The select now appends a synthetic option
  «{page_name} (текущая)» whenever the current value isn't in the
  catalog, so existing blocks keep their binding through edit/save.
  Sites that paginate per-entity blocks (procurements, programs,
  whatever) no longer need to register every synthetic page-name in
  the catalog just to make the editor save without bouncing.

## [0.51.4] — 2026-04-28

### Fixed
- **Block form: schema lookup falls back through `data.type`.**
  `pages/Blocks/Form.vue` previously matched a block's data schema by
  `block_type` only, so blocks that internally split shapes via
  `data.type` (e.g. a single `block_type='content'` row carrying
  `data.type='clubs-grid'`, `'text-card'`, …) could never get a
  proper visual editor — they fell through to the JSON textarea even
  when a schema was registered under their data-type key. Now
  `currentSchema` is resolved by `data.type` first, then `block_type`,
  so a partner-grid block edited as `content/clubs-grid` (legacy) and
  a future `clubs-grid`-typed block both pick up the same schema.
  Consumer sites must `npm run build` to ship the new admin bundle.

## [0.51.3] — 2026-04-27

### Fixed
- **`admin-core:migrate-to-document-list`** теперь после успешной миграции
  делает smoke-check consumer-вьюшек и предупреждает (с готовым diff'ом),
  если в `resources/views/components/page-blocks.blade.php` нет
  `@case('document-list')` рядом с `@case('links')`, или если
  `types/links.blade.php` не читает `$block->items` как fallback.
  Раньше команда переименовывала `block_type` в БД молча, и без правки
  consumer-views мигрированные блоки тихо переставали рендериться (как
  это случилось на ETU/meta.edu.kz 2026-04-22 — исчезли все ссылки на
  документы на sdg-resources, library, electronic-resources, about).
  Команда сама не патчит файлы (каждый сайт держит свой switch — у etec
  он 5128 строк, у ETU 386 — generic-патчер был бы хрупкий), а печатает
  copy-paste diff с точными правками.

### Added
- **`docs/MIGRATING-DOCUMENT-LIST.md`** — пошаговая инструкция: dry-run,
  apply, обязательные правки `page-blocks.blade.php` и `types/links.blade.php`,
  re-run safety, ссылки на референсный фикс в ETU.

## [0.48.0] — 2026-04-20

### Changed
- **Hero buttons URL field upgraded to `file` sub-type.** Editors can
  now paste an external URL / internal path OR upload a local file
  (PDF, DOC, XLS, ZIP, etc.) straight from the admin — the same UX
  used by `info-cards` / link-blocks. Uploaded file's URL is stored
  in the same `url` key, so `hero_buttons()` / `hero_buttons_zones()`
  / blade templates keep working without any change. Field label
  renamed to «Ссылка / Файл».
- No Vue changes — the `file` sub-type existed since v0.21.1.

## [0.47.0] — 2026-04-20

### Added
- **`select` sub-field type in `BlockDataEditor.vue`** — array items
  can now declare `type => 'select'` with an `options` array
  (`[{value, label}]` or plain strings) and the editor renders a real
  dropdown. Falls back to `—` when empty. Old bundles still work
  (the select branch fell through to the default text input before).
- **Hero buttons `position` is now a proper dropdown.** The 9 zones
  come with human-readable Russian labels in admin (Сверху слева /
  Сверху по центру / …). Backend slugs stay the same, so blade code
  using `hero_buttons_zones()` keeps working.

### Note for consumers
The Vue editor changed, so consumers must rebuild their admin bundle
(`npm run build`) to get the dropdown UI. Without a rebuild the
position field stays a plain text input — still writes the same slug,
just worse UX.

## [0.46.0] — 2026-04-20

### Added
- **Positional hero buttons.** Each row in the hero `buttons[]` array
  gains a `position` field (plain text input for now, with the allowed
  values listed in the label). 9 zones are supported:
  `top-left` / `top-center` / `top-right`,
  `center-left` / `center` / `center-right`,
  `bottom-left` / `bottom-center` / `bottom-right`.
  Default is `center`.
- **`hero_buttons_zones($data, $locale = null)` helper** returns the
  buttons grouped by zone (keys = 9 zone slugs, values = arrays of
  resolved buttons). Unknown / empty positions fall into `center`.
  Sibling to `hero_buttons()` — pick whichever fits the blade.
- **`hero_buttons()` now exposes `position`** on each returned row so
  consumers that don't use `_zones()` can still branch on it.

### Note
The `position` sub-field is a plain text input in admin — the array
editor doesn't render a `select` dropdown yet for array item fields.
Copy the value from the label hint. Proper dropdown ships once
`BlockDataEditor.vue` gains `select` sub-type support (and consumer
assets are rebuilt).

## [0.45.0] — 2026-04-20

### Added
- **Hero buttons repeater.** The `hero` block schema now includes an
  admin-editable `buttons[]` array so editors can add, remove and
  reorder CTAs without a code change. Each row has: `text`
  (multilingual — `translatable` sub-field, one string per locale),
  `url`, `icon` (FontAwesome class), `style` (raw CSS class string),
  `target` (`_self` / `_blank`). To disable a button — delete the
  row or clear its text in the target locale. Buttons render in
  order.
- **`hero_buttons($data, $locale = null)` helper** in the global
  namespace. Resolves the array into a locale-rendered list (drops
  rows without text or url, picks `text[locale]` with fallback to
  `app.fallback_locale` then the first non-empty value). Intended
  Blade usage:
  ```blade
  @foreach (hero_buttons($heroData) as $btn)
      <a href="{{ $btn['url'] }}" target="{{ $btn['target'] }}"
         class="{{ $btn['style'] }}">
          @if ($btn['icon']) <i class="{{ $btn['icon'] }}"></i> @endif
          {{ $btn['text'] }}
      </a>
  @endforeach
  ```

### Note for consumers
Schema change is **additive** — legacy `cta_label` / `cta_url` fields
stay in place and continue to render in any blade that reads them
directly. Adopt `buttons[]` per-page (or per-site) at your pace:
populate the array (via admin UI, seeder, or tinker), then switch
the corresponding blade to `hero_buttons()`.

## [0.44.0] — 2026-04-20

### Added
- **Admin user management** at `/admin/users` — list, create, edit,
  delete users and sync their spatie role. Model-agnostic: resolves
  the user class via `config('auth.providers.users.model')` so
  consumers keep their own `App\Models\User` with site-specific
  traits. Supports name/email search + role filter, unique-email
  guard on update (excluding current record), optional password
  change, self-delete guard. Like the permissions matrix this is a
  spatie-gated feature — the menu item only appears when
  `spatie/laravel-permission` is installed, and the controller
  `abort_unless` guards every method otherwise.
- **Rector Q&A workflow** at `/admin/rector-questions` — index/show/
  update/destroy for publicly-submitted questions to the rector.
  Filters: full-text search (first/last/email/subject/question),
  status (new/in_review/answered/rejected), category (education/
  research/international/youth/general). Tab counters (total/new/
  answered) for the Index header. Show page writes the answer,
  toggles publish, and auto-stamps `answered_at` when a question
  transitions to `answered`. Ships the `rector_questions` migration
  (guarded by `Schema::hasTable` so consumers that already created
  the table before v0.44 are skipped cleanly) and
  `Meta\AdminCore\Models\RectorQuestion` with `published()` /
  `newQuestions()` scopes plus `full_name` / `category_label`
  accessors.

### Changed
- Menu: adds «Вопросы ректору» under «Контент» (order 70) and
  «Пользователи» under «Система» (order 96, spatie-gated).

## [0.43.0] — 2026-04-19

### Added
- **Content import / export.** Two artisan commands let you move
  CMS data between installs (staging → prod, seed fresh instances,
  snapshot before risky edits):
  ```
  php artisan admin-core:export [--out=path.zip]
  php artisan admin-core:import path.zip [--mode=merge|replace] [--dry-run]
  ```
  Export bundles into a single ZIP:
  - `manifest.json` (version + row counts + resource list)
  - `page_blocks.json`, `menu_items.json`, `translations.json`,
    `taxonomy_terms.json`, `taxonomy_term_model.json`, `settings.json`
  - `resource.{name}.json` per registered `AdminCore::resource()`

  Import runs inside a transaction with FK checks off for the
  duration — row order inside the zip doesn't matter. `merge` upserts
  by primary key (keeps rows not in the dump); `replace` truncates
  each target first. `--dry-run` parses and reports counts without
  touching the DB.

## [0.42.0] — 2026-04-19

### Added
- **Live preview split.** The block edit form gets a «Предпросмотр»
  toggle in the tab bar. Turning it on pins a sidebar iframe that
  points at the public page the block belongs to
  (`/` for `home`, `/{slug}` otherwise). On every successful save the
  iframe refreshes — editors see their change in real context
  without leaving the admin. State persists in localStorage so the
  layout survives page reloads. Manual «обновить» + «открыть в
  новой вкладке» buttons sit next to the URL bar.

  Consumer Blade templates remain the source of truth — per-keystroke
  client-side rendering would require re-implementing every block
  template in Vue, which is specifically what this package avoids.

## [0.41.0] — 2026-04-19

### Added
- **Form builder.** Editors compose forms in the admin, the public
  site POSTs to the generated endpoint, submissions land in the DB.
  - Tables: `forms` (name/slug/fields-JSON/notify-email/
    success-message/is-active) and `form_submissions` (form_id/data/
    ip/user-agent/status, one row per POST).
  - `/admin/forms` — list + CRUD (Index.vue, Edit.vue).
  - `/admin/forms/{id}/submissions` — inbox view, status badges
    (new/read/replied/spam), inline status dropdown, CSV export.
  - Public endpoint: `POST /api/forms/{slug}`. Validation rules are
    derived from the fields schema (email→`email`, url→`url`,
    select→`in:`, etc). Returns 201 with `{ok, id, message}` on
    success so AJAX clients can show the configured thank-you text.
  - `notify_email` setting fires a best-effort plain-text mail on
    every submission (silently swallowed if the mail driver isn't
    configured — editors should never get "form broken" due to SMTP).
  - Supported field types: text, textarea, email, tel, url, number,
    date, select, radio, checkbox. Field editor lets you reorder,
    mark required, set placeholder/help, and — for select/radio —
    paste newline-separated options in `value=label` format.

## [0.40.0] — 2026-04-19

### Added
- **Taxonomies.** Polymorphic tag / category system so any model can
  be tagged without per-resource pivots.
  - `taxonomy_terms` — the vocabulary (type/slug/label, optional
    per-locale labels, sort order).
  - `taxonomy_term_model` — morph pivot.
  - `Meta\AdminCore\Concerns\Taxable` — opt-in trait. Provides
    `terms()` relation, `termsOfType($t)`, `syncTerms($type, $slugs)`
    (auto-creates missing terms on the fly), and
    `withTerm/withAnyTerm` query scopes.
  - `/admin/taxonomies` CRUD UI, one screen per vocabulary. Create
    new vocabularies inline by typing a new `type` name.
  - **Content API picks them up automatically.**
    `/api/content/articles?tag=interview,opinion` or `?category=admissions`
    filter the list (only when the model uses `Taxable`). Attached
    terms are serialized into each record under a `terms` key grouped
    by vocabulary: `{terms: {tag: [{slug,label}], category: […]}}`.

## [0.39.0] — 2026-04-19

### Added
- **Read-only Content API.** Exposes every registered resource as
  JSON so frontends (Next.js, mobile, SSG…) can decouple from Blade.
  Endpoints:
  ```
  GET /api/content/pages/{slug}
      → { page: {slug, locale}, blocks: [ {block_type, title, data, …} ] }

  GET /api/content/{resource}?page=1&per_page=20&locale=kk
      → paginated list (data + meta)
  GET /api/content/{resource}/{id_or_slug}
      → single record
  ```
  Locale resolution: `?locale=`, then `Accept-Language` header, then
  `config('app.locale')`, then first entry of `admin-core.locales`.
  Translatable fields are collapsed into the requested locale using
  the `translate()` method when the model provides it.

  Published status is enforced — `status='published'` or
  `is_published=true` is auto-applied when the model's table has
  that column, so drafts don't leak through the public API.
  Public by default; wrap the route group with `auth:sanctum` or
  throttle middleware in the consumer if you need to lock it down.

## [0.38.0] — 2026-04-19

### Added
- **Draft autosave.** New `useDraftAutosave(form, { key })` Vue
  composable debounce-saves any Inertia `useForm` instance into
  `localStorage` on every change. When the same form mounts again
  and the stored blob is newer than the record's last save, a
  banner offers to restore. Submitting successfully wipes the draft.

  Wired into `Blocks/Form.vue` by default — editors now get a
  «Несохранённая версия от HH:MM — Восстановить / Отбросить»
  prompt if a browser crash, tab close, or distracted copy-paste
  leaves them mid-edit.

  Purely client-side: no DB table, no per-user identity. Per-editor
  per-browser, which matches 95% of real crash-recovery flows.
  Consumers can opt their own forms in by calling the composable
  with a stable `key` per record.

## [0.37.0] — 2026-04-19

### Added
- **Webhooks.** Outbound HTTP callbacks on model CRUD events.
  ```php
  class Article extends Model {
      use \Meta\AdminCore\Concerns\Webhookable;
  }
  ```
  Events fire on `created`, `updated`, `deleted`, named
  `{table}.{action}` (e.g. `articles.updated`). The trait dispatches
  through `WebhookDispatcher`, which POSTs a JSON body to every
  webhook subscribed to the event:
  ```
  POST https://example.com/hook
  X-AdminCore-Event: articles.updated
  X-AdminCore-Hook-Id: 3
  X-AdminCore-Signature: sha256=…  (HMAC-SHA256 of body, when secret set)

  { "event":"articles.updated", "delivered_at":"…", "payload":{ … } }
  ```
  Ships with:
  - `webhooks` table migration (url, label, events, HMAC secret,
    is_active, last_fired_at).
  - `/admin/webhooks` CRUD screen with per-row "Тест" button,
    event-picker grouped by table, dirty HMAC-secret handling (empty
    input leaves the stored secret untouched).
  - `WebhookDispatcher::dispatch($event, $payload)` as the manual
    trigger for ad-hoc events not tied to a model save.
  - `PageBlock` adopts `Webhookable` out of the box.

  Failures are swallowed into the log (webhooks must never crash the
  admin); a non-responsive endpoint won't block editors. For at-
  least-once delivery, wrap the dispatcher in a queued job in your
  consumer.

## [0.36.0] — 2026-04-19

### Added
- **Public sitemap at `/sitemap.xml`.** Cached (1h TTL by default,
  configurable via `admin-core.sitemap.ttl`), standards-compliant
  `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`.
  Consumers declare URLs via a simple callback:
  ```php
  AdminCore::sitemapUrl(function () {
      return Page::where('status', 'published')->get()->map(fn ($p) => [
          'loc'        => url($p->slug),
          'lastmod'    => $p->updated_at->toIso8601String(),
          'changefreq' => 'weekly',
          'priority'   => '0.7',
      ]);
  });
  ```
  Multiple providers compose — register once per model/section.

### Changed
- **Locales are now config-driven.** `PageBlockController` and
  `MenuController` previously had a hardcoded
  `protected const LOCALES = ['ru','kk','en']`. Both now read from
  `config('admin-core.locales')` (already present in the published
  config). Form defaults use `array_fill_keys($locales, '')`,
  request-side persistence keys off the first locale instead of a
  literal `'ru'`. Opens the door to any 1..N locale setup — pass e.g.
  `['en']` for a monolingual site or `['en','fr','es','de']` for a
  four-language one. No migration needed: the row's `title/subtitle/
  content` columns keep storing the primary locale, the rest live
  in the polymorphic `translations` table.

## [0.35.0] — 2026-04-19

### Added
- **Focal-point smart cropping.** Editors mark where the *subject* of
  an image lives; the server crops around that anchor so the face /
  logo / landmark stays visible at every aspect ratio instead of
  getting guillotined by a plain centre-crop.

  - `ImageService::focalCrop(\$path, \$w, \$h, \$fx, \$fy)` — scales
    cover-style then crops around the focal point. Caches each variant
    at `{dir}/focal/{WxH_FX_FY}-{file}` so the disk is the cache;
    delete the cached file to regenerate. Silently returns the
    original path if intervention/image is missing.
  - `resources/js/components/FocalPointPicker.vue` — click-on-image
    picker. v-model is `{x, y}` in [0..1] (origin top-left). Shows a
    crosshair + guide lines, and includes a "reset to centre" link.

  Consumers wire the picker next to their existing image uploader and
  persist `{image}_focal_x` / `{image}_focal_y` columns — then call
  `focalCrop()` on the server when rendering cropped variants.

## [0.34.0] — 2026-04-19

### Added
- **Permissions matrix UI** at `/admin/permissions`. Role × resource ×
  action grid — per-cell checkboxes, per-row and per-column bulk
  toggles, dirty-aware save per role. Auto-creates the
  `{resource}.{action}` permissions for every registered resource on
  first load (idempotent) so the matrix is always full. Permissions
  that live outside the matrix (ad-hoc custom ones) are preserved on
  save rather than wiped.
  Hard-depends on `spatie/laravel-permission`; 404s gracefully and
  the sidebar item is hidden when the package isn't installed.

  Actions covered out of the box: `view`, `create`, `update`,
  `delete`, `publish`. Consumers extend the list by overriding
  `PermissionsController::ACTIONS` or wrapping the controller.

## [0.33.0] — 2026-04-19

### Added
- **Conditional fields.** Resource fields and attributes accept a
  `visible_when` key to hide themselves until a sibling takes a
  specific value, turning the schema into a lightweight rules engine:
  ```php
  AdminCore::resource('articles', [
      'attributes' => [
          ['name' => 'link_type', 'type' => 'select', 'options' => [
              ['value' => 'internal', 'label' => 'Внутренняя'],
              ['value' => 'external', 'label' => 'Внешняя ссылка'],
          ]],
          ['name' => 'external_url', 'type' => 'url', 'label' => 'URL',
           'visible_when' => ['field' => 'link_type', 'equals' => 'external']],
      ],
  ]);
  ```
  Supported operators per condition: `equals`, `not_equals`, `in`,
  `not_in`, `not_empty`, `empty`. Pass an array of conditions for AND
  semantics. Translatable fields compare against the currently active
  locale, so the same condition works across ru/kk/en.

## [0.32.0] — 2026-04-19

### Added
- **Revisions / version history.** Any model that adopts the
  `Revisionable` trait auto-snapshots its attributes into a polymorphic
  `revisions` table on every `updating` event, so editors get per-row
  undo. Comes with:
  - `Meta\AdminCore\Concerns\Revisionable` — trait with `revisions()`
    relation and `restoreRevision($id)` method. Opt-out via
    `$model::$revisionable = false` or a single-save `withoutRevision(fn
    () => …)` helper. Optional `$maxRevisions` caps retention.
  - `Meta\AdminCore\Models\Revision` — Eloquent model, immutable by
    convention. Stores `revisionable_{type,id}`, `user_id` (the editor
    at time of change), `data` JSON (pre-update snapshot), optional
    `note`.
  - `2026_04_19_000001_create_revisions_table` migration.
  - `/admin/{resource}/{id}/revisions` list screen + restore POST.
    Works for any resource registered via `AdminCore::resource()`.
  - `/admin/blocks/{id}/revisions` — dedicated route for the built-in
    PageBlock model, which lives outside the generic resource registry.
  - `resources/js/pages/Revisions/Index.vue` — Inertia page with
    "Показать / Восстановить" actions per revision.

### Changed
- **`PageBlock` adopts `Revisionable`** out of the box. Every edit to
  a block now leaves a trail; clicking «История изменений» in the
  edit form jumps to the new screen.

## [0.31.0] — 2026-04-19

### Added
- **Admin form UI for scheduled publishing.** `Blocks/Form.vue`
  gains two `datetime-local` inputs — «Опубликовать в» and
  «Снять с публикации в» — inside the Публикация card. Empty =
  behave as before (publish immediately, never auto-unpublish).
- `PageBlockController::validated()` accepts `publish_at` /
  `unpublish_at` (`date` + `after:publish_at`), `store()` and
  `update()` persist them, `presentForm()` formats them as
  `Y-m-d\TH:i` for the native datetime input.

## [0.30.1] — 2026-04-19

### Changed
- **`PageBlock` opts into `Publishable`** out of the box. Its
  `scopePublished` now honours `publish_at` / `unpublish_at`
  timestamps, and the bundled ticker command picks it up once the
  consumer adds the migration and calls
  `AdminCore::schedulable(PageBlock::class)`. `publish_at` and
  `unpublish_at` are added to `$fillable`.

## [0.30.0] — 2026-04-19

### Added
- **Scheduled publishing.** New `Publishable` trait + companion
  migration helper + `admin-core:apply-schedule` console command let
  editors pick a `publish_at` / `unpublish_at` timestamp per row.
  The ticker flips `status` between `'draft'` and `'published'`
  whenever the current time crosses one of the marks — idempotent,
  safe to run every minute:
  ```php
  class Article extends Model {
      use \Meta\AdminCore\Concerns\Publishable;
  }

  // AppServiceProvider::boot()
  AdminCore::schedulable(\App\Models\Article::class);

  // bootstrap/app.php  (Laravel 12 scheduler)
  ->withSchedule(fn (Schedule $s) =>
      $s->command('admin-core:apply-schedule')->everyMinute())
  ```
  Migration side uses `PublishableSchema::columns($table)` — drops
  two timestamp columns (`publish_at`, `unpublish_at`) with indexes.

  Query scopes ship with the trait: `->published()` returns rows
  visible *right now* (status published AND publish_at past AND
  unpublish_at future/null); `->scheduled()` lists upcoming
  publications; `->duePublish()` / `->dueUnpublish()` drive the
  ticker. Fully backwards compatible — models that don't opt in
  are unaffected.

## [0.29.0] — 2026-04-19

### Added
- **`DefaultBlockCatalog::enabledPageSlugs()` extension hook.** Lets
  consumer sites plug a per-page boolean toggle into the admin
  «Страница» dropdown without reimplementing the whole catalog.
  Override it to return a slug whitelist (typically driven by a
  `pages.status` column or a settings row); anything outside the
  whitelist vanishes from the picker. Default `null` means "no
  filtering" — fully backwards compatible.
  ```php
  class MyCatalog extends DefaultBlockCatalog
  {
      protected function enabledPageSlugs(): ?array
      {
          return Page::where('status', 'published')->pluck('slug')->all();
      }
  }
  ```

## [0.28.0] — 2026-04-19

### Added
- **`POST /admin/logout` route + `LogoutController`.** The shipped
  `AdminLayout.vue` has always posted here on "выйти", but the
  package never defined a matching route — consumers hit 404.
  Now owned by the package. Inertia-aware: when called with an
  `X-Inertia` header (which is always the case from the admin SPA)
  the controller returns `Inertia::location('/')` so the browser
  does a full-page navigation instead of rendering the public home
  inside the admin shell. Vanilla (non-Inertia) callers still get a
  plain `redirect('/')`.

### Changed
- **`/admin/blocks` index.** Default sort switched from
  `page_name asc, sort_order asc` to `updated_at desc, id desc` so
  the block you just edited sits at the top. `groupBy('page_name')`
  downstream preserves iteration order, so the whole page with the
  freshest edit bubbles up.
- **`/admin/blocks` search** now matches a much wider surface:
  `subtitle`, `content`, raw `data` JSON, per-locale values in the
  polymorphic `translations` table, and page slugs whose
  `BlockCatalog` label or group name contains the term. Typing
  "документы" finds blocks on a page labeled "Отчёты и прозрачность"
  — not just exact slug/title hits as before.

## [0.27.0] — 2026-04-18

### Added
- **Typed block DTOs.** `PresentedBlock` gains three typed subclasses —
  `HeroBlock`, `LinksBlock`, `StatsBlock` — each with explicit field
  accessors and normalized defaults so templates drop the `?? []`,
  `isset()`, `is_array()` boilerplate:
  ```blade
  @foreach ($block->items() as $link)            {{-- LinksBlock --}}
      <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
  @endforeach
  @foreach ($block->buttons() as $btn)           {{-- HeroBlock --}}
      <a href="{{ $btn['url'] }}">{{ $btn['text'] }}</a>
  @endforeach
  ```
  All subclasses extend `PresentedBlock`, so existing magic accessors
  (`$block->title`, `$block->links`, `$block->stats`) keep working —
  migration is purely additive.

- **`BlockTypeRegistry`** maps `block_type` → DTO class, with a public
  `register()` hook so consumers can extend from a service provider:
  ```php
  BlockTypeRegistry::register('pricing', \App\Content\Blocks\PricingBlock::class);
  ```

- **`PageBlockResolver::present()`** — static factory that instantiates
  the right DTO for a raw block record. The resolver's `->get()` now
  routes through it, so every block returned by `page_blocks(...)->get()`
  is already the typed variant when one is registered.

  Unknown block types fall back to the generic `PresentedBlock` — adding
  a new block type is zero-friction, shipping a typed variant later is
  a purely additive change.

## [0.26.0] — 2026-04-18

### Fixed
- **`PresentedBlock::__isset` now mirrors `__get`'s lookup chain.** Before,
  `isset($block->block_key)` / `isset($block->is_active)` returned `false`
  for model attributes, which silently broke Laravel Collection helpers
  like `$blocks->firstWhere('block_key', 'vision')` and
  `$blocks->where('is_active', true)` (both use `data_get → isset`).
  Symptom on consumers: sub-partials that cross-reference sibling blocks
  (e.g. `mission.blade.php` pulling its paired `vision` block) rendered
  nothing. Covered by new regression tests
  (`test_isset_mirrors_get_including_model_attributes`,
  `test_collection_firstWhere_finds_by_model_attribute`).

### Notes
- **Readonly prop shadow gotcha.** `PresentedBlock` declares readonly props
  `id`, `key`, `type`, `title`, `subtitle`, `content`, `status`, `sort`,
  `locale`. These are accessed directly (never through `__get`), so if a
  block's raw `data` has a key with the same name (commonly `data['type']`
  for content sub-routing like `'accreditation-status'`), `$block->type`
  returns the outer `block_type` ("content"), not the inner data value.
  Templates that need the data value must use `$block->data['type']`
  (or `$block->raw('type')`). The bulk `data['x']` → `x` rewrite cannot be
  applied blindly for these reserved names. Pinned by
  `test_readonly_props_shadow_conflicting_data_keys`.

## [0.20.0] — 2026-04-18

### Added
- **`SdgFeature` shipped as a built-in feature module.** Mirrors
  `GreenDealFeature`: toggles `sdg-goals` + `sdg-news` resources
  (17 UN sustainability goals + news items linked to them) on at
  `/admin/features`. Requires `App\Models\SdgGoal` +
  `App\Models\SdgNews` on the consumer.

### Fixed
- **Feature-module resources were missing their per-resource routes.**
  `AdminCoreServiceProvider::booted()` used to register feature
  modules AFTER `routes/admin.php` enumerated
  `AdminCore::getResources()`, so resources declared by modules
  (like `gdc-pages`) never got `index / create / edit / update /
  destroy` routes emitted. Now features register BEFORE admin
  routes load. `/admin/gdc-pages` 200s instead of 404.

## [0.19.2] — 2026-04-18

### Added
- **Image bubble menu in `RichTextEditor.vue`.** Clicking an image
  in the Tiptap editor opens a floating toolbar with Replace /
  Alt / 50%-75%-100% width / Delete. Selected image gets a red
  outline. `BubbleMenu` import moved to `@tiptap/vue-3/menus`
  (its v3 subpath).

## [0.19.1] — 2026-04-18

### Fixed
- **Resource/Index table: Actions column no longer collapses to 0
  width on long titles.** Switched to `table-fixed` + `<colgroup>`
  with explicit column widths. Dropped `sticky top-16` on the
  thead (broke alignment when inside `overflow-hidden` wrapper).

## [0.19.0] — 2026-04-18

### Added
- **Mobile-first responsive list UI.** `Resource/Index.vue` now renders
  as a proper HTML table on `md+` screens with fixed-width columns
  (Status 144px, Actions 144px, sticky header) and a stacked card
  list below `md`. Card rows show image + title + inline
  Edit/Show-hide/Delete text-link actions. No more invisible
  action icons on narrow viewports.
- **Two-column form layout.** `Resource/Form.vue` splits into main
  content (translatable fields) on the left and a right sidebar
  (320px) for image + metadata attributes on `lg+`. On mobile
  everything stacks and a sticky bottom action bar holds Save/
  Cancel buttons so they stay reachable. Attributes can opt into
  the main column via `group: 'content'` or `main: true` in the
  resource config.
- **Collapsible sidebar on desktop.** New toggle at the top of the
  sidebar shrinks it to 64px (icon-only). State persisted in
  `localStorage` so it survives reloads.
- **Cmd+K / Ctrl+K command palette** (`CommandPalette.vue`). Fuzzy
  filter over all registered nav items, arrow-key navigation,
  Enter to go. Search button in the header opens it, `⌘K` badge
  hints at the shortcut.
- **Toast notifications** (`FlashToasts.vue`). Inertia flash props
  (`success`/`error`/`info`) pop as sliding toasts in the bottom
  right, auto-dismiss after 4s. Legacy inline banners removed
  from the layout. Consumers can fire ad-hoc toasts via
  `window.dispatchEvent(new CustomEvent('admin-toast', { detail: {...} }))`.

### Changed
- Table actions column always visible on desktop (144px fixed
  width, `whitespace-nowrap`) — previously collapsed to 0px when
  titles were long, hiding Edit/Delete icons.
- Header gets a proper search button and truncates long user
  emails on narrow screens.
- Action buttons on list rows now have `title=` tooltips.

## [0.18.2] — 2026-04-18

### Fixed
- **Activity log descriptions decode multi-locale titles.** When a
  logged model's `title`/`name` stored `{"ru":"...","kk":"..."}`
  JSON (PageBlock and friends), the raw JSON leaked into the
  description, rendering as literal curly-brace noise on
  `/admin/activity`. The ActivityController now unwraps the JSON on
  the fly in `humanizeDescription()` — historical rows render
  cleanly without any data migration.

## [0.18.1] — 2026-04-18

### Changed
- **`DefaultBlockCatalog` now ships the full META-University block
  library** — 8 page groups (~30 pages) and 11 block-type categories
  (~60 types: hero, stats, FAQ, timeline, admission steps, team
  cards, GDC sections, …). Previously the default only had 3 pages
  and 7 types, making the page builder unusable out of the box —
  every consumer had to ship its own catalog just to create
  anything. Now new sites get a working page builder from day one.
  Consumer sites that want to customize still rebind the
  `BlockCatalog` contract.

## [0.18.0] — 2026-04-18

### Added
- **Full admin suite shipped from the package.** Seven more admin
  subsystems moved out of per-site `App\Http\Controllers\Admin\Spa`
  into `Meta\AdminCore\Http\Controllers`:
  - `ActivityController` (`/admin/activity`) + `ActivityLog` model
  - `BackupController` (`/admin/backup`) — SQLite + storage zips
  - `CacheController` (`/admin/cache`) + `CacheService` with
    config-driven groups (`config('admin-core.cache_groups')`)
  - `LeadController` (`/admin/leads`) + `Lead` model + migration
    + `LeadCreated` event for consumer-side notifications
  - `MenuController` (`/admin/menu`) + `MenuItem` model + migration
  - `PageBlockController` (`/admin/blocks`) + `PageBlock` model +
    migration. The UI catalog (pages, block types, labels) is now
    injected via `Meta\AdminCore\Contracts\BlockCatalog`; a minimal
    `DefaultBlockCatalog` ships with the package, consumers rebind
    the contract to their own catalog class.
  - `SiteSettingsController` (`/admin/site-settings`) — social links,
    rector contact, secondary logo, menu toggles. Menu keys and
    social networks come from
    `config('admin-core.site_settings.*')`.
- **Translation infrastructure** moved into the package:
  `Meta\AdminCore\Models\Translation` + `Meta\AdminCore\Concerns\Translatable`
  trait. Consumer models can opt into it without touching
  `App\Traits\Translatable`.
- `LeadCreated` event for admin notifications / CRM push — keeps the
  model free of per-site notification classes.

### Changed
- Sidebar auto-adds seven more «Система» items: Заявки, Активность,
  Бэкапы, Общие, Меню, Блоки, Кэш (alongside Настройки / Медиа /
  Тема сайта / Фичи / Обновления from v0.17.0).

## [0.17.0] — 2026-04-18

### Added
- **Theme subsystem shipped by the package.** Moved `ThemeService` +
  `ThemeController` + `config/theme.php` from per-site `App\Services`
  / `App\Http\Controllers\Admin\Spa` into the package so every
  consumer gets the same token schema and admin UI on
  `composer update`. DB override merging is now recursive so the
  admin UI can override any token, not just `primary`/`accent`.
  Routes: `GET /admin/theme`, `PUT /admin/theme`, `POST /admin/theme/reset`.
- **Media library shipped by the package.** New
  `Meta\AdminCore\Models\Media` (table `media_legacy`),
  `Meta\AdminCore\Services\ImageService` (WebP conversion via
  Intervention Image with graceful fallback), `MediaController`
  (list/upload/rename/delete), plus migration
  `2026_04_18_000006_create_media_legacy_table.php`.
  Routes: `GET|POST /admin/media`, `PUT|DELETE /admin/media/{medium}`.
- **`/media/{path}` public fallback route** in `routes/public.php` —
  serves files from `public/media/` or `storage/app/public/`. Fixes
  the "broken thumbnail everywhere" issue on Plesk installs where
  Nginx intercepts `/storage/`.
- **`media_url()` helper** autoloaded from the package (no more per-
  site `App\Helpers\helpers.php` copies).
- **Settings controller shipped by the package.** New
  `Meta\AdminCore\Models\Setting` (cache-aware, with `get()`/`set()`
  helpers), `SettingsController` with grouped Inertia UI + locale
  tabs, `SettingUpdated` event for consumer-side side-effects
  (e.g. syncing `university_name` into a hero block).
  Routes: `GET /admin/settings`, `PUT /admin/settings/{id}`.
- Sidebar auto-adds "Медиа" (fa-photo-film) and "Тема сайта"
  (fa-palette) under "Система".

### Changed
- `create_settings_table` migration now ships `description` (TEXT)
  instead of `label` + `sort_order` — matches the schema existing
  consumer sites already have so fresh installs don't diverge.

## [0.16.0] — 2026-04-18

### Added
- **Packaged feature modules.** New `Meta\AdminCore\Features\FeatureModule`
  abstract class + built-in `GreenDealFeature` module. The package now
  ships the registration logic for optional features, so consumer sites
  don't need to re-declare `AdminCore::resource(...)` blocks in each
  `AppServiceProvider`. Add a module to `builtInFeatures()` in the
  service provider once → all 100+ consumer sites pick it up on
  `composer update`.
- **Admin UI for feature toggles** at `/admin/features`. Each feature
  is a card with a switch; toggle writes `feature.<name>` to the
  `settings` table. Unavailable features (missing model, missing
  migration) render as disabled cards with a reason banner instead of
  crashing at boot.
- **DB override for feature flags.** `AdminCore::enabled($name)` now
  consults the `settings` table before falling back to
  `config('admin-core.features.*')` / `.env`. This means an admin
  toggle survives deploys and overrides `.env`.
- Sidebar auto-adds "Фичи" (fa-toggle-on) and "Обновления"
  (fa-cloud-arrow-down) under the "Система" group.

### Changed
- Built-in `GreenDealFeature` replaces the per-site
  `AdminCore::whenEnabled('green_deal', ...)` block that consumers
  (ETU, etec) used to carry in `AppServiceProvider`. Those blocks can
  now be removed.

## [0.15.0] — 2026-04-18

### Added
- **Feature toggles.** New `config('admin-core.features')` array and
  `AdminCore::enabled($name)` / `AdminCore::whenEnabled($name, fn)`
  helpers let consumer sites enable or disable optional admin modules
  per environment:
  ```php
  AdminCore::whenEnabled('sdg', function () {
      AdminCore::resource('sdg-goals', [...]);
      AdminCore::resource('sdg-news',  [...]);
  });
  ```
  ```env
  FEATURE_SDG=true
  FEATURE_GREEN_DEAL=false
  FEATURE_LIBRARY=true
  FEATURE_PROJECTS=false
  ```
- Default flags in `config/admin-core.php` — always-on (news, articles,
  pages, blocks, schools, programs, teachers, management, vacancies,
  leads, redirects) and opt-in (sdg, green_deal, library, catalog,
  projects, rector_questions).


## [0.14.1] — 2026-04-18

### Added
- **User edit modal** on the Users page. Pencil button opens a dialog
  with name / email / role / password fields. Consumer apps still
  need to add a `PUT /admin/users/{user}` route pointing at their
  UserController::update (package routes don't ship for this since the
  User model is consumer-specific).


## [0.14.0] — 2026-04-18

### Added
- **Package-shipped migrations.** Fresh Laravel installs get the base
  tables out of the box (`translations`, `activity_logs`, `settings`,
  `redirects`, `contacts`). Each migration has a `Schema::hasTable()`
  guard so existing consumers with their own schema aren't disturbed.
- Auto-loaded via `loadMigrationsFrom` in the service provider — just
  `php artisan migrate` and they run.
- Also exposed for publishing: `php artisan vendor:publish
  --tag=admin-core-migrations` copies them into the consumer's
  `database/migrations` if they want to edit/extend.


## [0.13.0] — 2026-04-18

### Added
- **`php artisan admin-core:install` — one-command setup.** In a fresh
  Laravel 11/12 app after `composer require meta/admin-core`, this
  does everything:
  1. Publishes `config/admin-core.php` + Inertia root view
     (`resources/views/admin/inertia.blade.php`).
  2. Scaffolds `resources/js/admin-spa.js` + `resources/css/admin-spa.css`
     from stubs (Tailwind v3/v4 auto-detected from `package.json`).
  3. Patches `bootstrap/app.php` to register
     `Meta\AdminCore\Http\Middleware\HandleInertiaRequests`.
  4. Patches `vite.config.js` — adds `admin-spa.{js,css}` to
     `laravel({input})`, adds `@admin-core` alias, enables
     `preserveSymlinks`.
  5. Runs `php artisan migrate --force`.
  6. Creates `admin@example.com` / `password` admin user.
  7. Runs `npm install` for the missing deps (Vue, Inertia, Tiptap,
     FontAwesome, Inter font) and `npm run build`.
- Flags: `--force` (overwrite), `--no-npm` (skip Node step), `--no-user`
  (skip admin user creation).
- Stubs at `meta/admin-core/stubs/` let consumers see the default
  scaffolding and override per-site before `:install` by copying over.


## [0.12.2] — 2026-04-18

### Changed
- **Empty state is contextual.** Instead of a bare "Записей не найдено",
  the empty list now shows:
  - a helpful title + hint depending on whether a filter is active,
    search is active, or the table is just empty,
  - a "Создать" button that preserves the filter,
  - a "Сбросить фильтр" shortcut when filtered.
- **Subtitle reflects the filter context.** When
  `/admin/management?school_id=5` returns nothing, the page subtitle
  shows "Школа: ПЦК Экономика и право" instead of the unhelpful
  "Всего: 0".
- **Filter banner explains what happens next.** Adds a small helper line
  telling the user Create adds with that filter + Reset sees all.


## [0.12.1] — 2026-04-18

### Added
- **Human-readable filter labels.** Filter banner on the index list
  now shows resolved names instead of raw IDs. Declare `label` + an
  optional `resolver` closure:
  ```php
  'filters' => [
      'school_id' => [
          'type'     => 'exact',
          'label'    => 'Школа',
          'resolver' => fn ($id) => School::find($id)?->name,
      ],
  ],
  ```
  Banner becomes "Показаны только: Школа: Экономика и право" instead of
  "school_id=5".


## [0.12.0] — 2026-04-18

### Added
- **`badges` config on resources** — per-row visual indicators in the
  index list. Each badge has `when` (closure), `label`, `icon`, `color`:
  ```php
  'badges' => [
      ['when' => fn ($t) => $t->is_deputy, 'label' => 'Заместитель декана', 'icon' => 'fa-user-shield', 'color' => 'purple'],
  ],
  ```
  Rendered next to the row title. Colours: amber/red/green/blue/purple/gray.
- **`dim` config** — closure returning bool. Rows where it's true render
  opacity-60 and get a "Скрыт с сайта" badge. Typical use:
  ```php
  'dim' => fn ($m) => !$m->is_active,
  ```
- **Filter pre-fill on Create.** Clicking "Создать" from a filtered
  index (`/admin/teachers?school_id=5`) pre-fills matching attributes
  on the create form, so you don't re-enter the school. Also the
  "Сбросить фильтр" button on the list header + a banner showing which
  filters are active.


## [0.11.0] — 2026-04-18

### Changed
- **Resource form redesigned as stacked sections.** Instead of a 2/3 +
  1/3 (main/sidebar) grid that split translatable fields away from
  their related plain attributes, the form now renders a single
  max-w-5xl column of section cards. Each section can contain both
  translatable fields (e.g. `dean_name` with locale tabs) and plain
  attributes (e.g. `dean_email`, `dean_office`) next to each other.
  Sections derived from `group` on both `fields` and `attributes`.
- Locale tabs moved to a sticky strip at the top of the form — one set
  for the whole page, applies to all translatable fields everywhere.
- Image uploader shows below sections. Save/cancel in a sticky footer
  row instead of sidebar card.

### Rationale
Schools edit had 13 plain attributes in a tall sidebar column while
translatable `dean_name` was in the main area — same-entity data split
across the page. Matches the pre-headless Blade admin layout where
"Декан", "Методист", "Приёмная комиссия" were unified sections.


## [0.10.0] — 2026-04-18

### Added
- **`group` on attributes** — declare logical groups and each one
  renders as its own card in the form sidebar, with an optional icon
  in the heading. Solves the "sidebar is a 13-field vertical column"
  problem on resources like Schools and Teachers.
  ```php
  ['name' => 'dean_email', 'type' => 'email', 'label' => 'Email',
    'group' => 'Декан', 'group_icon' => 'fa-user-tie'],
  ['name' => 'dean_office','type' => 'text',  'label' => 'Кабинет',
    'group' => 'Декан'],
  ```
- Attributes without `group` land in a default "Атрибуты" card (old
  behaviour preserved).
- `group_icon` on the first attribute of each group shows next to the
  heading.


## [0.9.0] — 2026-04-18

### Added
- **Filters on resource index.** Declare allowed query-string filters on
  a resource config; the package applies them to the list query.
  ```php
  'filters' => [
      'menu_item' => 'exact',                          // WHERE menu_item = value
      'category'  => ['type' => 'like'],               // WHERE category LIKE %value%
      'status'    => ['column' => 'post_status', 'type' => 'in'], // whereIn
  ],
  ```
  Typical use: sidebar items linking to `/admin/articles?menu_item=international`
  now actually filter (before this, the query string was ignored).


## [0.8.0] — 2026-04-18

### Added
- **In-admin updater.** New `/admin/updates` page in every consumer site
  (menu item auto-registers from the package). Shows installed vs
  latest version, fetches changelog from GitHub, and has a one-click
  "Update" button that runs composer/artisan/npm server-side.
- `Meta\AdminCore\Services\UpdateChecker` — polls `api.github.com/repos/
  sirserik/meta-admin-core/releases/latest`, caches for 1h.
- `UpdateController::run()` — runs:
  1. `composer update meta/admin-core -W --no-interaction`
  2. `php artisan migrate --force`
  3. `config:clear / route:clear / view:clear / cache:clear`
  4. `npm run build` (if npm is available locally)
  Writes a full log to `storage/logs/admin-core-update-{Y-m-d}.log` and
  shows the last log on the page.
- Environment probe on the page — shows whether composer/npm/shell_exec
  are actually available, so admin knows before clicking Update.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>

## [0.7.0] — 2026-04-18

### Added
- **All specialised admin pages moved into the package** (`resources/js/pages/`):
  Activity, Backup, Blocks, Cache, Leads, Media, Menu, RectorQuestions,
  Settings, SiteSettings, Theme, Users. 16 Vue files previously duplicated
  byte-for-byte across ETU and etec consumer apps.
- Consumer sites no longer need local copies of these pages. The
  `bootAdminCore` glob already resolves them from the package via
  `corePages = import.meta.glob('../../vendor/meta/admin-core/resources/js/pages/**/*.vue')`.
- Site-specific overrides still possible — just drop a file at
  `resources/js/admin-spa/pages/{Name}/Index.vue` and it wins.

### Changed
- Dropped Dashboard.vue demo content; Dashboard now renders stats +
  recent-items + quick-actions from 0.6.0.


## [0.6.0] — 2026-04-18

### Added
- **Enriched dashboard.** The package's `Dashboard.vue` now renders:
  - KPI cards with optional `url` (clickable, links to a resource) and
    `trend` subtitle (e.g. "Новые: 3").
  - "Recent items" widgets — latest N rows per resource registered via
    the new `AdminCore::dashboardRecent('news', ['label' => …, 'limit' => 5])`.
    Each row links to its edit page; widget header links to the index.
  - Quick actions row — shortcut buttons registered via
    `AdminCore::dashboardQuickAction(['label' => …, 'url' => …, 'icon' => …])`.
- Standardised dashboard across consumer sites: drop the per-site
  Dashboard.vue overrides, configure from `AppServiceProvider` and all
  sites look identical.

### Changed
- `DashboardController` now queries the top-N rows for each registered
  `dashboardRecent` provider and passes them to the Vue page along with
  stats + quickActions.


## [0.5.1] — 2026-04-18

### Fixed
- **Save/delete failed for resources whose model uses a non-`id` route
  key** (e.g. News uses `slug` via `getRouteKeyName()`). Vue form posted
  `PUT /admin/news/{item.id}` but the per-resource routes look up the
  model by `route_key` → 404.
- `presentRow` and `presentForm` now include `_route_key` (the value
  returned by `$m->getRouteKey()`). Both `Resource/Form.vue` (submit)
  and `Resource/Index.vue` (delete, toggle-publish) use it. Falls back
  to `id` for backward-compat.


## [0.5.0] — 2026-04-18

### Added
- **IconPicker component + `icon` attribute type.** New standard way to
  pick FontAwesome icons in the admin, so every site doesn't roll its
  own plain text input:
  ```php
  ['name' => 'icon', 'type' => 'icon', 'label' => 'Иконка']
  ```
  Renders an input with live preview + a "Выбрать" button that opens
  a searchable modal with ~200 curated FA icons grouped by category
  (Общее / Навигация / Контент / Образование / Бизнес / …). Users can
  also type any FA class name manually.
- Exported as `@admin-core/components/IconPicker.vue` for site-specific
  Vue pages that need an icon picker outside the generic Resource form
  (e.g. Menu, Settings).


## [0.4.1] — 2026-04-18

### Added
- **`edit_url` config key on resources** — closure that overrides the
  default `/admin/{resource}/{id}/edit` URL on the index list. Row
  clicks go directly to the URL you return. Useful when a resource is
  a façade over another (e.g. Pages → PageBlocks) and the intermediate
  edit screen is just noise.
  ```php
  'edit_url' => fn ($item) => '/admin/blocks?page=' . $item->page_key,
  ```


## [0.4.0] — 2026-04-18

### Added
- **`actions` config key on resources.** Declare extra CTAs (buttons or
  banner) on the edit form so the same look ships from the package for
  every site. Use case: "Edit blocks" button on Pages resource — the
  real content lives in another resource, and every site using the
  package now gets the same prominent banner without writing Vue code
  per-site.
- Resource config entry shape: `['label', 'icon', 'url' (string |
  closure), 'description', 'primary']`. Closure URLs are resolved
  per-request with the current Eloquent model.
- `Resource/Form.vue` renders `primary => true` actions as a gradient
  banner at the top of the form, and non-primary ones as small buttons
  in the header next to "К списку".
- Docs: new "Actions" section in `docs/resources.md`.


## [0.3.5] — 2026-04-18

### Fixed
- **Critical: resource-name resolution in ResourceController.** With the
  per-resource route registration from v0.3.4, routes use
  `->defaults('resource', $name)` together with a URL placeholder
  `{id}`. Laravel's DI injects controller method args **positionally**,
  so `edit(string $resource, string $id)` received `$resource='1'`
  (the URL param) and `$id='pages'` (the default) — swapped. Every
  `/admin/{name}/{id}/edit`, `/admin/{name}/{id}` (PUT/DELETE/PATCH)
  returned 404 because `AdminCore::getResource('1')` is null.
- All controller actions (index/create/edit/store/update/destroy/
  togglePublish) now pull the resource name from
  `$request->route()->parameter('resource')` via a new
  `resolveResource()` helper. Works regardless of arg order.


## [0.3.4] — 2026-04-18

### Fixed
- **Critical: per-resource routes instead of catch-all `{resource}`.**
  The generic `GET /admin/{resource}` catch-all was eating every
  consumer-specific path (e.g. `/admin/activity`, `/admin/leads`) —
  the router matches patterns in registration order, so the wildcard
  won. Now `routes/admin.php` enumerates `AdminCore::getResources()`
  and registers narrow routes per resource name. Consumer-specific
  routes registered in `routes/web.php` win over package ones.
- **Load routes after `$app->booted()`.** Required because consumer
  `AppServiceProvider::boot()` is where resources get registered via
  `AdminCore::resource()`. Loading the package routes during the normal
  boot phase meant the registry was empty when we iterated it.
- **Removed `admin.resource.*` named routes.** They were lost with the
  per-resource registration. `ResourceController` now uses `url()`
  with the configured prefix for redirects; `admin_core_route()` helper
  was rewritten to compute URLs directly without named-route lookup.


## [0.3.3] — 2026-04-18

### Fixed
- **Critical: add `web` middleware group to admin routes.** Without it the
  consumer app's session/cookies/CSRF middleware never run for
  `/admin/*`, so `auth()` can't find the logged-in user and every
  protected admin page 302s to `/login` in an endless loop. Symptom in
  consumer apps: after login the user is bounced back to login when
  visiting `/admin`. Fix: `routes/admin.php` now prepends `web` to the
  configured middleware array (idempotent via `array_unique`).


## [0.3.2] — 2026-04-18

### Fixed
- **Legacy `/admin/dashboard` URL now redirects to `/admin`.** Before this
  fix, consumer apps with old code or tests pointing at `/admin/dashboard`
  hit the generic `ResourceController` with `resource=dashboard`, which
  404'd because no resource of that name is registered. The package now
  ships an explicit 302 redirect from `/admin/dashboard` → `/admin`
  (honours the configured `prefix`).

## [0.3.1] — 2026-04-18

### Added
- **Full documentation suite** in `docs/` (17 files, ~3600 lines):
  installation, quickstart, resource API reference, translatable fields,
  attribute types (with per-type validation rules), dynamic FK selects,
  images, navigation & dashboard, validation, routing, custom Vue pages,
  extending the core, architecture diagrams, migration from legacy Spa
  controllers, upgrade guide between versions, troubleshooting, package
  development.
- Top-level `README.md` rewritten as concise landing page with TOC
  linking to `docs/`.

### Changed
- No code changes. Docs-only release.

## [0.3.0] — 2026-04-18

### Added
- **CHANGELOG.md** documenting the 0.1 → 0.2 release history.
- **Expanded README** with a config reference table, attribute type
  reference, dynamic FK select example, unique-validation note, and a
  migration guide from legacy Spa controllers.
- **Test suite** — `phpunit/phpunit ^11.0` as dev dep, `tests/` directory,
  `composer test` script. Initial `AdminCoreRegistryTest` covers resource
  registration defaults, navigation grouping, icon prefixing, and
  dashboard-stat provider filtering (6 tests / 32 assertions).

### Changed
- `require-dev` and `autoload-dev` added to composer.json so tests only
  run in the package repo, never on consumer installs.

## [0.2.2] — 2026-04-17

### Added
- **Closure-based `options` for select attributes.** Enables dynamic FK
  selects without hard-coding values at boot time:
  ```php
  ['name' => 'school_id', 'type' => 'select', 'options' => fn () =>
      School::orderBy('name')->get(['id', 'name'])
          ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all()]
  ```
  `ResourceController::resolveAttributeOptions()` evaluates callables on
  each request.

## [0.2.1] — 2026-04-17

### Added
- `unique => true` attribute flag generates a `unique:{table},{column},{id}`
  validation rule (excluding current row on update).

## [0.2.0] — 2026-04-17

### Added
- **Typed attributes.** `attributes` array replaces the flat `plain` list.
  Each attribute specifies `type` (`text`, `url`, `email`, `number`, `date`,
  `datetime-local`, `select`, `boolean`, `color`, `textarea`), `label`,
  `required`, `placeholder`, `help`, `options`, `max`.
- Automatic validation rules derived from `type` + `required` + `max`.
- `SimpleField.vue` component renders typed attributes in the sidebar.

### Changed
- Generic `Resource/Form.vue` now reads `attributes` prop alongside
  `fields` (translatable). Old-style `plain` arrays still accepted for
  back-compat.

## [0.1.0] — 2026-04-17

### Added
- Initial release. **Resource API** — consumer apps register admin modules
  with a single `AdminCore::resource(name, config)` call.
- Auto-wired routes: `/admin/{resource}` catch-all dispatched through a
  single `ResourceController` that reads config from the registry.
- Generic Vue pages: `Resource/Index.vue` (list + search + pagination) and
  `Resource/Form.vue` (create/edit with locale tabs + image upload).
- Spatie Translatable integration — fields listed in `translatable` are
  read/written via `translations` table.
- Image field support with `media_url()` helper.
- `AdminCore::menuItem()` for non-resource admin pages.
- `AdminCore::dashboardStat()` for dashboard cards.
- `HandleInertiaRequests` middleware shares nav/auth/brand to all pages.
- Publishable `config/admin-core.php` and root `app.blade.php` view.
- Tiptap-based `RichTextEditor.vue` component (ProseMirror) replaces TinyMCE.
