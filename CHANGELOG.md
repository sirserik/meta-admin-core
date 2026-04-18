# Changelog

All notable changes to `meta/admin-core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
