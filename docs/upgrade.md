# Upgrade guide

Breaking changes and migration steps between versions.

## 0.2.x → 0.3.0

**No breaking changes.** Docs + tests additions only. Just bump the
constraint:

```json
"meta/admin-core": "^0.3"
```

## 0.1.x → 0.2.x

### Breaking: `plain` → `attributes`

0.1 took scalar fields as a flat `plain` array of column names. 0.2
expects typed entries under `attributes`:

```php
// Before (0.1)
'plain' => ['slug', 'is_published', 'published_at'],

// After (0.2+)
'attributes' => [
    ['name' => 'slug',         'type' => 'text'],
    ['name' => 'is_published', 'type' => 'boolean'],
    ['name' => 'published_at', 'type' => 'datetime-local'],
],
```

The old `plain` array is still read as a fallback, but it produces no
form input (no label, no type) — treat it as deprecated and convert.

### New: typed validation

Validation rules are now derived from attribute types. Previously the
controller just ran `nullable` on every plain field. If your model
relied on strict-string-only values for columns that are actually
numeric/date/email, no-op. Otherwise type them properly and invalid
input will now be rejected at the validator.

### New: `unique` flag

```php
['name' => 'slug', 'type' => 'text', 'unique' => true]
```

Generates `unique:{table},slug,{id}`. Opt-in; doesn't affect existing
resources.

## 0.2.1 → 0.2.2

### New: closure-based `options`

`options` on `select` attributes can now be a callable:

```php
['name' => 'school_id', 'type' => 'select',
    'options' => fn () => School::orderBy('name')->get(['id', 'name'])
        ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all()
]
```

No change needed to existing static `options` arrays.

## Version skew in consumer apps

If you have both a path repo and a VCS repo configured:

```json
"repositories": [
    { "type": "path", "url": "/absolute/path", "options": { "canonical": false, "versions": { "meta/admin-core": "0.3.0" } } },
    { "type": "vcs", "url": "https://github.com/sirserik/meta-admin-core.git" }
]
```

- **Local dev:** Composer uses the path repo's symlink; your edits to
  the package are reflected immediately (no `composer update` needed).
- **Prod (no path):** Composer falls back to the VCS repo and downloads
  a tagged release.

After tagging a new version in the package repo, run
`composer update meta/admin-core -W` in each consumer to bump the lock
file on the VCS side.

## Tag discipline

The package follows semver:

- **Patch** (`0.2.1` → `0.2.2`): no breaking changes, just features /
  bug fixes that don't change the config shape.
- **Minor** (`0.2.x` → `0.3.0`): new features, may introduce new config
  keys; existing configs keep working.
- **Major** (`0.x` → `1.0`): breaking changes spelled out in this guide.

Pre-1.0 releases MAY contain breaking changes on minor-version bumps —
read the CHANGELOG.

## Inertia 2 → 3

The package declares `inertiajs/inertia-laravel: ^2.0 || ^3.0`. Both
work. If you bump the consumer app's Inertia, be aware that Inertia 3
changed a few client-side APIs (like `router.visit` options). The
package doesn't call these directly, but any custom Vue pages you wrote
might need adjustment.

## Laravel 11 → 12

Both are supported. No changes required in the package config or the
consumer's resource registrations. Just bump Laravel per its own
upgrade guide.
