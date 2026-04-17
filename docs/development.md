# Package development

How to work on `meta/admin-core` itself.

## Clone and install

```bash
git clone https://github.com/sirserik/meta-admin-core.git
cd meta-admin-core
composer install
```

## Running tests

```bash
composer test
# or
./vendor/bin/phpunit
```

Current suite: `tests/AdminCoreRegistryTest` — 6 tests / 32 assertions,
runs in under 20 ms. Pure PHPUnit, no Laravel bootstrap.

## Test coverage goals

- Registry behavior (registration, defaults, navigation grouping)
- `ResourceController` rule generation per attribute type
- `presentRow` / `presentForm` output shape
- Closure-based `options` resolution

Not in scope (would require Laravel + a DB): full end-to-end CRUD
through a live Laravel testbench. Could be added via
`orchestra/testbench` if the investment is worth it.

## Local development against a consumer app

Link a consumer's admin to the local package instead of the published
version:

```json
// consumer-app/composer.json
"repositories": [
    {
        "type": "path",
        "url": "/Users/you/Desktop/meta-admin-core",
        "options": {
            "symlink": true,
            "canonical": false,
            "versions": { "meta/admin-core": "0.3.0" }
        }
    },
    {
        "type": "vcs",
        "url": "https://github.com/sirserik/meta-admin-core.git"
    }
]
```

Then:

```bash
composer update meta/admin-core -W
```

Now `vendor/meta/admin-core` is a symlink to your working copy. Edit
PHP/Vue files in the package and they take effect immediately in the
consumer app (for Vue, restart Vite to rebuild).

**Important:** `preserveSymlinks: true` in `vite.config.js` — otherwise
Vite resolves through the symlink and picks up duplicate Vue instances.

Before committing, unlink and test against the tagged version to
confirm nothing diverged.

## Release a new version

1. Branch off `main`, land changes via PRs.
2. Update `CHANGELOG.md` — move `[Unreleased]` to a dated section.
3. Commit and push to `main`.
4. Tag:

    ```bash
    git tag v0.3.1 -m "patch: ..."
    git push origin main v0.3.1
    ```

5. Run tests in each consumer app's CI (if any). Bump `composer.json`
   constraints if warranted (`^0.3` stays valid for all 0.3.x).

No registry to publish to — Composer pulls directly from GitHub via the
VCS repo in consumer `composer.json`.

## Semver policy

- `patch` (0.3.0 → 0.3.1): no breaking changes, no config shape changes.
- `minor` (0.3.x → 0.4.0): new features, possibly new config keys or
  new attribute types. Existing configs keep working.
- `major` (0.x → 1.0): breaking changes. Document in docs/upgrade.md.

Pre-1.0, minor bumps are allowed to break — but only if there's no
reasonable alternative. When in doubt, ship it as major.

## Adding a new attribute type

Say you want `money` (formatted currency input).

1. **`SimpleField.vue`** — add a branch:

    ```vue
    <input v-else-if="type === 'money'"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value.replace(/[^\d.]/g, ''))"
        inputmode="decimal"
        class="..." />
    ```

2. **`ResourceController::ruleForAttribute()`** — add the case:

    ```php
    'money' => "{$base}|numeric|min:0",
    ```

3. **Optional: `presentForm()`** — format on output if needed.

4. **Tests** — extend `AdminCoreRegistryTest` or add a new test class.

5. **Docs** — add the type to `docs/attributes.md`.

6. **CHANGELOG** — document under `[Unreleased]`.

## Adding a new Vue component

Drop it under `resources/js/components/`, export from the `@admin-core`
alias. If it's a standalone widget that consumers might want to import
directly:

```js
// In consumer app
import MyNewWidget from '@admin-core/components/MyNewWidget.vue';
```

Keep component APIs stable — they're part of the public surface.

## Style guidelines

### PHP

- PSR-12 formatting.
- No abstract patterns or dependency-injection frameworks beyond what
  Laravel ships. The package is deliberately minimal.
- Type-hint everything.
- Docblocks only when they add info beyond the signature.

### Vue

- `<script setup>` everywhere, Vue 3 composition API.
- Tailwind utility classes inline. No separate SCSS files.
- No state library (Pinia) — Inertia props are the state.
- Props validated via runtime `defineProps({ … })` types.

### Route and controller design

- Single entry point per action (generic catch-all).
- Keep `ResourceController` thin — dispatch, validate, save. Business
  logic belongs in models / observers / services.

## Publishing docs updates

Docs live in `docs/`. Changes to docs are minor bumps at most (they don't
affect the config API). No separate docs site — GitHub renders the
Markdown.

## CI

Not configured yet. To add:

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: ${{ matrix.php }} }
      - run: composer install
      - run: composer test
```

## Bug reports

Open an issue with:

- Laravel version
- PHP version
- Package version / commit
- Minimal reproduction: the `AdminCore::resource()` config + the
  expected vs actual behavior
- Error message + relevant stack trace

For security issues, email `dev@meta.edu.kz` instead of opening a
public issue.
