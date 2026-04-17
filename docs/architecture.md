# Architecture

How the pieces fit together when a request hits `/admin/articles`.

## Request lifecycle

```
Browser                    Laravel                       Vue
                                                         
GET /admin/articles ──►   web middleware stack           
                          │                              
                          ▼                              
                  HandleInertiaRequests.share()          
                  — auth, flash, locale, navigation, brand
                          │                              
                          ▼                              
                  Route: GET /admin/{resource}           
                  → ResourceController@index             
                          │                              
                          ▼                              
                  AdminCore::getResource('articles')    
                  — config from memory registry         
                          │                              
                          ▼                              
                  Article::query()                       
                  ->orderBy(order_by)                    
                  ->paginate(per_page)                   
                  ->transform(presentRow)                
                          │                              
                          ▼                              
                  Inertia::render('Resource/Index', [...])
                          │                              
                          ▼                              
                     (first load)                        
                  admin-core::app.blade.php              
                  + props as JSON in data-page          
                          │                              
                          ▼                              
                  ─────────────────────── HTTP ─────────►
                                                         
                                                         bootAdminCore
                                                         ├─ resolve page
                                                         │  (sitePages → corePages)
                                                         ├─ attach AdminLayout
                                                         └─ mount
```

Subsequent navigation is XHR — Inertia sends the new props as JSON,
the SPA swaps the current page component. The `admin-core::app` shell
loads once.

## The registry

`Meta\AdminCore\AdminCore` is a **singleton** registered as
`admin-core` in the container. It holds three in-memory stores:

- `resources: array<string, array>` — keyed by name
- `menuItems: array<int, array>`
- `dashboardStats: array<int, callable>`

Populated in consumer apps via `AdminCore::resource/menuItem/dashboardStat`
calls — typically inside `AppServiceProvider::boot()`. Lives for the
lifetime of the PHP worker process.

```
┌─────────────────────────────────┐
│ AppServiceProvider::boot()       │
│  ─ AdminCore::resource('…')      │
│  ─ AdminCore::resource('…')      │
│  ─ AdminCore::menuItem('…')      │
│  ─ AdminCore::dashboardStat(fn)  │
└──────────────┬──────────────────┘
               │   register at boot
               ▼
┌─────────────────────────────────┐
│  AdminCore (container singleton)│
│   resources: [...]              │
│   menuItems: [...]              │
│   dashboardStats: [...]         │
└──────────┬────────┬─────────────┘
           │        │
           │        ▼
           │    HandleInertiaRequests.share()
           │        ─ navigation: AdminCore::navigation()
           │
           ▼
       ResourceController
           ─ getResource($name) by URL segment
```

## Components

### Service provider

`AdminCoreServiceProvider::boot()`:

- Loads `routes/admin.php`
- Registers the `admin-core` view namespace
- Publishes config, Vue assets, and the root Blade view

It does **not** register the Inertia middleware globally — that's the
consumer's responsibility, to avoid conflicting with their own.

### Routes

One file, `routes/admin.php`, mounts the catch-all:

```
GET    /admin                                   admin.spa.dashboard
GET    /admin/{resource}                        admin.resource.index
GET    /admin/{resource}/create                 admin.resource.create
POST   /admin/{resource}                        admin.resource.store
GET    /admin/{resource}/{id}/edit              admin.resource.edit
PUT    /admin/{resource}/{id}                   admin.resource.update
PATCH  /admin/{resource}/{id}                   (alias of update)
DELETE /admin/{resource}/{id}                   admin.resource.destroy
PATCH  /admin/{resource}/{id}/toggle-publish    admin.resource.toggle-publish
```

The prefix and middleware come from `config('admin-core.{prefix,middleware}')`.

### ResourceController

Singleton, plain Laravel controller. Each action looks up the resource
config, drives Eloquent, and returns `Inertia::render(...)`.

Key internals:

- `config($name)` — fetch + 404 check
- `applySearch($q, $term, $config)` — single-term LIKE search
- `validated($request, $config, $existing)` — builds rules array
- `ruleForAttribute($a)` — per-type rule dispatch
- `fill($m, $data, $request, $config, $existing)` — write to model
- `saveTranslations($m, $data, $config)` — persist per-locale values
- `presentRow($m, $config)` — list-view row
- `presentForm($m, $config)` — edit-view payload
- `mediaUrl($path)` — `media_url()` helper or Storage fallback

### Inertia middleware

`HandleInertiaRequests` sets the root view to `admin-core::app` and
shares:

- `auth.user` with `roles[]`
- `flash.{success,error}`
- `locale`
- `navigation` — from `AdminCore::navigation()`
- `brand` — from config

Consumer apps either append this middleware, or subclass it to share
more props.

### Vue entry point

`bootAdminCore({sitePages, corePages, AdminLayout, title})` is the
consumer-facing API. It:

1. Starts `createInertiaApp`
2. Resolves page components: **site first**, then core fallback
3. Attaches `AdminLayout` as the default layout

```
page name               "Articles/Index" (from config.page + action)
                               │
                               ▼
  sitePages['./admin-spa/pages/Articles/Index.vue']?
  corePages['../../vendor/meta/admin-core/resources/js/pages/Articles/Index.vue']?
                               │
                               ▼
                    dynamic import → mount
```

### Vue components (package-shipped)

Located in `resources/js/components/`:

- `AdminLayout.vue` — sidebar + top bar + content slot
- `PageHeader.vue` — page title with `#actions` slot
- `LocaleTabs.vue` — `v-model` for the active locale
- `TranslatableField.vue` — per-locale input with error display
- `SimpleField.vue` — plain attribute input (all types)
- `RichTextEditor.vue` — Tiptap wrapper
- `Pagination.vue` — Inertia-aware pager

## Data flow on save

```
POST /admin/articles
  multipart body {
     title[ru], title[kk], ...,
     slug, is_published, featured_image (file), ...
  }

ResourceController::store()
  ├─ config = AdminCore::getResource('articles')
  ├─ data   = $this->validated($request, $config)
  │           └─ rules = [ image, remove_image, translatable locale fields, attributes ]
  │
  ├─ $m = new Article
  ├─ $this->fill($m, $data, $request, $config)
  │    ├─ plain & attributes → setAttribute
  │    ├─ slug auto-generate if empty
  │    ├─ image upload → store under 'articles/'
  │    └─ physical-column mirroring
  │
  ├─ $m->save()
  ├─ $this->saveTranslations($m, $data, $config)
  │    └─ for each locale: $m->saveTranslations($locale, $payload)
  │
  └─ redirect→ admin.resource.index with success flash
```

## File layout

```
meta-admin-core/
├── composer.json                 — package metadata
├── phpunit.xml                   — test config
├── CHANGELOG.md
├── README.md                     — quickstart + TOC
├── config/admin-core.php         — publishable config
├── routes/admin.php              — auto-loaded
├── stubs/                        — reserved for future scaffold stubs
├── src/
│   ├── AdminCore.php             — registry singleton
│   ├── AdminCoreServiceProvider.php
│   ├── Facades/AdminCore.php
│   ├── helpers.php               — admin_core_route()
│   └── Http/
│       ├── Controllers/
│       │   ├── DashboardController.php
│       │   └── ResourceController.php
│       └── Middleware/
│           └── HandleInertiaRequests.php
├── resources/
│   ├── views/app.blade.php       — Inertia root
│   ├── css/admin-core.css
│   └── js/
│       ├── admin-spa.js          — bootAdminCore
│       ├── layouts/AdminLayout.vue
│       ├── components/
│       │   ├── PageHeader.vue
│       │   ├── LocaleTabs.vue
│       │   ├── TranslatableField.vue
│       │   ├── SimpleField.vue
│       │   ├── RichTextEditor.vue
│       │   └── Pagination.vue
│       └── pages/
│           ├── Dashboard.vue
│           └── Resource/
│               ├── Index.vue
│               └── Form.vue
├── tests/
│   └── AdminCoreRegistryTest.php
└── docs/                         — this documentation
```

## Design principles

1. **Config over code.** Adding a resource should never require writing
   a controller or a Vue page for the common CRUD case.
2. **Gradual adoption.** Legacy specialised controllers coexist with
   generic-resource ones. Migrate one at a time.
3. **Site overrides package.** Every Vue page is overridable by the
   consumer without patching the package.
4. **No hidden global state.** Everything flows through the registry
   and config. Inspecting `AdminCore::getResources()` and
   `php artisan route:list` shows you the full picture.
5. **Pluggable persistence.** Translations and images are integration
   points, not hard requirements — the package is useful even for a
   single-locale, no-image resource.

## Non-goals

- Role/permission management (use Spatie Permission or the like).
- Dashboard charts (use your preferred lib and a custom page).
- Soft deletes UI (plain CRUD by design — extend for restore/purge).
- Import/export (custom controller + Artisan command works fine).
- Theming beyond the `brand.color` accent.
