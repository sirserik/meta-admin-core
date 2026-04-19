# Changelog

All notable changes to `meta/admin-core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
