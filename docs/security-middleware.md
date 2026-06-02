# Security & runtime middleware (v1.7)

Four reusable middleware, all registered as aliases. Two can also auto-attach
to the `web` group via config (zero wiring).

| Alias | Class | Purpose |
|---|---|---|
| `admin-core.security-headers` | `SecurityHeaders` | nosniff / X-Frame-Options / Referrer-Policy / Permissions-Policy, optional HSTS + CSP |
| `admin-core.honeypot` | `HoneypotProtection` | hidden-field + min-time spam trap for public forms |
| `admin-core.admin` | `EnsureUserIsAdmin` | gate the admin panel to admin-capable users |
| `admin-core.redirects` | `HandleRedirects` | apply admin-managed URL redirects on GET |

## Security headers

```env
ADMIN_CORE_SECURITY_HEADERS=true   # auto-append SecurityHeaders to the web group
ADMIN_CORE_HSTS=true               # add HSTS (HTTPS only)
CSP_ENFORCE=false                  # CSP starts as Report-Only
CSP_REPORT_URI=/csp-report         # optional
```

CSP is opt-in: it emits a header only when you fill `admin-core.security.csp.directives`
(a `name => value` map), and it is skipped on the admin prefix (the SPA needs
inline/eval). Example in `config/admin-core.php`:

```php
'csp' => ['enforce' => env('CSP_ENFORCE', false), 'directives' => [
    'default-src' => "'self'",
    'img-src'     => "'self' data: https:",
    'script-src'  => "'self' 'unsafe-inline'",
]],
```

## Honeypot

```html
<input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">
<input type="hidden" name="_form_time" value="{{ time() }}">
```
```php
Route::post('/lead', ...)->middleware('admin-core.honeypot');
```
Field name + min seconds: `admin-core.security.honeypot.{field,min_seconds}`.

## Admin gate

`admin-core.admin` requires auth, then: a Gate ability `admin-core.access-admin`
if defined, else spatie `hasAnyRole(admin-core.admin_roles)`, else any
authenticated user. Configure roles via `admin-core.admin_roles`.

## Redirects

```env
ADMIN_CORE_REDIRECTS_RUNTIME=true   # auto-append HandleRedirects to the web group
```
Rules live in the `redirects` table (managed via the admin redirects resource).
Active rules are cached 5 min; a match issues `redirect(to_url, status_code)`
and increments `hits` (column added by the v1.7 migration; the middleware
no-ops if the table/column is absent).
