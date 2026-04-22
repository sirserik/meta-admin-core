# Admin password recovery (PIN-gated)

Off-band rescue flow for admins who've lost access to the normal
mail-based password reset — SMTP is down, the admin's inbox is
unreachable, or the outage is mid-incident and there's no time to wait
on email delivery. Enter a shared PIN, then set a new password.

## Enabling the feature

The whole flow is **disabled by default**. The `/admin/recover` routes
respond `404` until you set `ADMIN_RESET_PIN` in `.env`:

```env
# 16+ characters, random, never committed
ADMIN_RESET_PIN=v7p9-QxC2-Lm8k-r4Bn-zH6dNqW1
```

That's it — deploy, clear config cache, visit
`https://your-site/admin/recover`. Leave the var empty and the feature
stays invisible.

Optional tuning (defaults shown):

```env
ADMIN_RESET_VERIFIED_TTL=300     # sec the "PIN verified" flag lives
ADMIN_RESET_PIN_ATTEMPTS=5       # PIN / password attempts per window
ADMIN_RESET_PIN_DECAY=3600       # window length in seconds
ADMIN_RESET_PASSWORD_MIN=12      # minimum chars for the new password
```

## Flow

1. **GET** `{prefix}/recover` — PIN input form.
2. **POST** `{prefix}/recover` — constant-time compared to
   `config('admin-core.recovery.pin')`. On success the session gets a
   `verified_at` timestamp; session id regenerated. On failure the
   attempt is logged and rate-limited.
3. **GET** `{prefix}/recover/password` — new-password form. Middleware
   checks the session flag is less than `verified_ttl` seconds old; if
   not, redirects back to step 1.
4. **POST** `{prefix}/recover/password` — validates email + password,
   checks the user has one of the `allowed_roles`, updates the password
   via `forceFill`, nukes `remember_token`, clears the session flag,
   regenerates the session, redirects to the login route (if defined).

`{prefix}` defaults to `admin` but follows `config('admin-core.prefix')`
just like the rest of the admin.

## Security posture

* Feature self-404s when `ADMIN_RESET_PIN` is unset or shorter than
  `min_pin_length` (default 8). Un-configured servers don't advertise
  the endpoint at all.
* `hash_equals(hash('sha256', $expected), hash('sha256', $given))` —
  constant-time compare that never lets early-byte mismatches leak
  through timing.
* Rate limiter `admin-recovery` (registered by the package) — per-IP,
  5 attempts / 3600 sec by default; both the PIN POST and the password
  POST share the same limiter so a PIN-holder can't brute-force emails
  separately.
* Role check: `config('admin-core.recovery.allowed_roles')` defaults to
  `['admin', 'super-admin']`. If the consumer uses
  `spatie/laravel-permission` and the user class has `hasAnyRole`, the
  check runs; otherwise it's skipped gracefully (no silent failure).
* Generic error on bad email: "Пользователь не найден или недостаточно
  прав." — PIN-holder cannot enumerate admin accounts.
* Every attempt (bad PIN, good PIN, refused reset, successful reset)
  goes through `Log::` at appropriate levels, so the trail lives in
  whatever channel the consumer has configured.
* Session id rotated on PIN verification and again after password
  change, so stealing an old cookie mid-flow doesn't help.

## Overriding the views

Both blades live under the package view namespace:

```
admin-core::auth.recovery-pin
admin-core::auth.recovery-password
admin-core::auth._layout            {{-- minimal self-contained shell --}}
```

They use inline CSS with a single `--brand` custom property pulled from
`config('admin-core.brand.color')` so the shell matches your site
without requiring the consumer's Vite bundle to be built. If you want
full brand fidelity, publish and customize:

```bash
php artisan vendor:publish --tag=admin-core-views
# → resources/views/admin-core/auth/*.blade.php
```

## Logging

All log lines share the prefix `admin-core recovery:`. Grep the default
log channel:

```
admin-core recovery: bad PIN          → ip, ua
admin-core recovery: PIN verified     → ip
admin-core recovery: reset refused    → ip, email_given, reason
admin-core recovery: password reset   → ip, user_id, email
```

## When NOT to use this

* You're building a multi-tenant SaaS — a per-site PIN is too coarse.
  Use the normal reset flow plus a break-glass procedure scoped to
  platform ops.
* You can't rotate the PIN on every personnel change — that's your
  actual risk surface. Keep `ADMIN_RESET_PIN` on the same rotation
  cadence as production database credentials.

## Rotating the PIN

Rotate by editing `.env` and clearing the config cache:

```bash
sed -i.bak 's/^ADMIN_RESET_PIN=.*/ADMIN_RESET_PIN=<new-value>/' .env
php artisan config:clear && rm -f bootstrap/cache/config.php
```

The old PIN stops working instantly on the next request. Any in-flight
"PIN verified" session flags expire normally (within `verified_ttl`
seconds) — they don't grant retroactive access to a new reset.
