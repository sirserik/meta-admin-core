# Changelog

All notable changes to `meta/admin-core` are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
