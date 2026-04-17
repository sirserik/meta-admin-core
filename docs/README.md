# meta/admin-core — Documentation

Comprehensive reference for building admin panels with `meta/admin-core`.

## Who this is for

Developers integrating the package into a Laravel site (**consumer app**), or
extending the package itself.

## Table of contents

### Getting started
- [Installation](installation.md) — set up the package in a fresh or
  existing Laravel app: composer config, Vite, middleware, layouts.
- [Quickstart: first resource](quickstart.md) — register an admin module
  in under 5 minutes with a single `AdminCore::resource()` call.

### Core concepts
- [Resource API reference](resources.md) — every config key documented,
  with defaults and examples.
- [Translatable fields](fields.md) — main-area form fields (`text`,
  `textarea`, `editor`) with per-locale inputs.
- [Attribute types](attributes.md) — sidebar scalar fields (`select`,
  `boolean`, `date`, `color`, etc.) with validation behavior.
- [Dynamic FK selects](select-options.md) — closure-based `options` for
  foreign-key dropdowns.
- [Images](images.md) — `image_field`, storage, `media_url()`, upload
  flow, delete-on-destroy.
- [Navigation & dashboard](navigation.md) — `menuItem()`, sections,
  ordering, `dashboardStat()`, branding.
- [Validation](validation.md) — auto-generated rules, `required`,
  `unique`, `max`, error display.
- [Routing](routing.md) — the `/admin/{resource}` catch-all, URL shape,
  named routes, `admin_core_route()` helper.

### Customisation
- [Custom Vue pages](custom-pages.md) — override the generic
  `Resource/Index.vue` / `Resource/Form.vue` for a single resource.
- [Extending the core](extending.md) — tapping into the registry from
  your own code.

### Reference
- [Architecture](architecture.md) — how the registry, ResourceController,
  Inertia middleware, and Vue entry wire together.
- [Migration from legacy Spa controllers](migration.md) — step-by-step
  conversion recipe.
- [Upgrade guide](upgrade.md) — breaking changes by version.
- [Troubleshooting](troubleshooting.md) — common errors and their fixes.

### Contributing
- [Package development](development.md) — running tests, adding
  field types, releasing versions.

## Version

These docs describe `v0.3.0`. See [CHANGELOG](../CHANGELOG.md) for history.
