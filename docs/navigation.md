# Navigation & dashboard

How the sidebar, dashboard cards and branding are assembled.

## Sidebar composition

The sidebar is built from **two sources**:

1. **Registered resources** — any `AdminCore::resource()` call auto-adds
   a sidebar entry. Its `menu` key decides which section it lands in.
2. **Ad-hoc menu items** — `AdminCore::menuItem()` for screens that
   aren't simple resources (settings, media library, etc.).

Both sources are merged, grouped by `menu`, and ordered by the `order`
key. The result is a list of `{ section, items }` objects passed to
every Inertia page via the `navigation` shared prop.

## Registering a menu item

```php
AdminCore::menuItem(
    label: 'Бэкапы',
    href:  '/admin/backup',
    icon:  'fa-database',
    menu:  'Система',
    order: 77,
);
```

| Parameter | Default      | Purpose                                |
|-----------|--------------|----------------------------------------|
| `label`   | —            | Sidebar text                           |
| `href`    | —            | Link target (absolute path or full URL)|
| `icon`    | `fa-circle`  | FontAwesome class                      |
| `menu`    | `'Другое'`   | Section header                         |
| `order`   | `100`        | Sort key within the section (lower → top) |

## Sorting

Within a section, items sort by `order` ascending. Resources default to
`order: 50`, so menu items with `order < 50` appear **above** resources,
and `order > 50` **below**. A reasonable scheme:

| Range    | Content                            |
|----------|------------------------------------|
| 1–9      | Dashboard, main entry points       |
| 10–40    | Resources by feature area          |
| 50       | Default (for resources)            |
| 60–80    | System links (settings, backups)   |
| 90+      | Rare utilities, dev tools          |

## Section groups

Sections are just strings — create whatever grouping fits the site. A
typical meta site uses:

- `Главное` — Dashboard, Activity log
- `Контент` — Articles, News, Pages, Blocks, Menu
- `Образование` — Schools, Programs, Teachers, Management, Vacancies
- `ЦУР (SDG)` — SDG-specific resources
- `Обращения` — Leads, Rector questions
- `Медиа` — Media library
- `Система` — Users, Settings, Theme, Cache, Backups, Redirects
- `Библиотека` — Catalog, Categories

## Icons

FontAwesome 6 classes. If you pass `fa-newspaper`, the package prepends
`fas`. If you pass `fas fa-newspaper` or `far fa-book`, it's used as-is.

## Link targets

`href` is the literal `<a href>` value. The Vue sidebar uses Inertia
`<Link>` for same-origin paths and plain `<a>` otherwise — this is
handled in `AdminLayout.vue`.

Query strings are fine:

```php
AdminCore::menuItem('Международное', '/admin/articles?menu_item=international',
    'fa-globe', 'Образование', 24);
```

## The "active" state

Highlighting the current sidebar item is done by the Vue layout by
comparing `page.url` to each link's `href`. No PHP involvement.

## Dashboard cards

`/admin` renders a grid of stat cards, built from closures registered
via:

```php
AdminCore::dashboardStat(fn () => [
    'label' => 'Статей',
    'value' => \App\Models\Article::count(),
    'icon'  => 'fa-newspaper',
]);
```

The callback runs once per request. Return **`null`** to skip the card
conditionally (e.g. based on user role):

```php
AdminCore::dashboardStat(function () {
    if (!auth()->user()->hasRole('admin')) return null;
    return ['label' => 'Пользователей', 'value' => User::count(), 'icon' => 'fa-users'];
});
```

Other callback shape keys:

| Key       | Type     | Purpose                                        |
|-----------|----------|------------------------------------------------|
| `label`   | string   | Card title                                     |
| `value`   | string\|int | Main number                                 |
| `icon`    | string   | FontAwesome class                              |
| `href`    | string   | Optional — link the whole card somewhere       |
| `trend`   | float    | Optional — percentage change shown as badge    |

(Any additional keys are passed to the Vue component as-is.)

### Cache the counts

If your stats run expensive queries, cache:

```php
AdminCore::dashboardStat(fn () => [
    'label' => 'Заявок за неделю',
    'value' => cache()->remember('admin:dashboard:leads:week', 300,
        fn () => Lead::where('created_at', '>', now()->subWeek())->count()
    ),
    'icon' => 'fa-inbox',
]);
```

## Branding

Shown in the sidebar header (logo char + title + subtitle). Configured
via env or published `config/admin-core.php`:

```env
ADMIN_BRAND_NAME="META University"
ADMIN_BRAND_SUBTITLE="Admin"
ADMIN_BRAND_COLOR="#C41E3A"
ADMIN_BRAND_LOGO_CHAR="M"
```

The `color` is used as the accent (buttons, spinners, focus rings) — it
reaches the Vue side via the shared `brand` prop and CSS custom
properties set by the layout.

## Per-user sidebar

The navigation prop is built per request. If you need role-gated items,
wrap the registration:

```php
if (auth()->user()?->hasPermissionTo('manage users')) {
    AdminCore::menuItem('Пользователи', '/admin/users', 'fa-users', 'Система', 70);
}
```

Or build a post-filter in `HandleInertiaRequests`:

```php
'navigation' => function () use ($request) {
    $nav = AdminCore::navigation();
    // filter out items this user shouldn't see
    return $nav;
}
```

## Overriding the layout

If you need a different sidebar structure entirely, pass a different
`AdminLayout` to `bootAdminCore`:

```js
import AdminLayout from './components/MyCustomAdminLayout.vue';
bootAdminCore({ sitePages, corePages, AdminLayout });
```

Your layout receives the `navigation` and `brand` shared props from the
Inertia middleware.
