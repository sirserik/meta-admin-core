# FirewallFeature — SSH allow-list from the admin panel

Manage which IPs may reach **SSH (port 22)** from `/{prefix}/firewall`, without
ever giving the web process any privilege. Designed for self-hosted nodes
running **ufw**.

## Why this design

A compromised admin panel must not be able to reconfigure the server firewall.
So the responsibilities are split:

- The admin page (and `FirewallController`) **only read/write the
  `firewall_rules` table**. No sudo, no shell, no privilege.
- A **root cron script** reconciles `ufw` with that table once a minute. Root
  never executes any application PHP — the script is a tiny standalone bash
  file that reads the DB and runs `ufw`. Smallest possible attack surface.
- **Emergency addresses** are baked into the script, so the allow-list can never
  go empty and you can never lock yourself out. Prefer a whole block over a
  single address: a dynamic IP moves inside its ISP's range, and one changed
  octet must not cost you SSH.
- A **failed read of the table never removes anything.** The desired set is
  unknown then, and «unknown» must not be read as «nothing» — see below.

The page itself is a **self-contained Blade view, not the admin SPA** — on
purpose. It is a break-glass tool you may need precisely when the SPA build is
broken or your IP just changed.

## Enable

```env
FEATURE_FIREWALL=true
FIREWALL_EMERGENCY_IP=203.0.113.0/24,198.51.100.7   # yours — NEVER blocked; list, block or single
# FIREWALL_UFW_COMMENT=admin-core-allowlist   # optional
# FIREWALL_GATE_MIDDLEWARE=ops.pin            # optional step-up gate alias
```

```bash
php artisan migrate            # creates firewall_rules (guarded if it exists)
```

The sidebar gets a **Сервер → SSH-доступ** link to `/{prefix}/firewall`.

## Install the root sync script (the one privileged step)

The package never performs privileged operations from PHP. Install the cron
script **as root**:

```bash
sudo php artisan admin-core:firewall-sync-script --path=/usr/local/sbin/admin-core-firewall-sync
( sudo crontab -l 2>/dev/null; \
  echo '* * * * * /usr/local/sbin/admin-core-firewall-sync >> /var/log/admin-core-firewall.log 2>&1' ) \
  | sudo crontab -
```

`--path` writes the file (mode 0700) instead of printing it. Prefer it over
`> file`: anything PHP prints first — a deprecation notice from a config file,
a warning from an extension — would otherwise end up above the shebang and the
cron job would run a broken script.

The generated script has this site's values baked in (emergency addresses,
`.env` path, table, ufw comment); **DB credentials are read from `.env` at
runtime**, so they stay correct if they rotate. It supports `pgsql`,
`mysql`/`mariadb` and `sqlite` (read from `DB_CONNECTION`).

Re-generate the script whenever the emergency list changes — it is baked in at
generation time, not read from `.env` on every run.

> First make sure ufw is active and your current IP is allowed, or add it via
> the page's "Разрешить SSH с моего текущего IP" button before tightening
> `ufw default deny incoming`.

## Config

```php
// config/admin-core.php
'firewall' => [
    // comma-separated; baked into the script at generation time
    'emergency_ip' => env('FIREWALL_EMERGENCY_IP'),
    'table'        => 'firewall_rules',
    'ufw_comment'  => env('FIREWALL_UFW_COMMENT', 'admin-core-allowlist'),
    'gate'         => env('FIREWALL_GATE_MIDDLEWARE'), // optional step-up middleware
],
```

## What happens when the database is unreachable

Nothing is removed. The script ensures the emergency addresses are present,
logs the client's own error and exits non-zero, leaving `ufw` exactly as it
was.

This is not hypothetical. On a META node a wrong `.env` was deployed over the
right one; `psql` started failing on authentication, the failure was swallowed
by `2>/dev/null`, and the empty output was read as «the table wants no
addresses» — so the next cron tick deleted every allowed source for port 22
and cut SSH off from everyone but the baked-in address. The reconcile step now
runs only after the table was actually read; the regression is covered by
`tests/Unit/FirewallSyncScriptTest.php`, which drives the real script against
fake `ufw`/`psql` binaries.

## Notes

- Only **IPv4** and IPv4/CIDR are accepted (a ufw v4 source rule). Garbage is
  rejected in the controller, in the script generator, and independently
  re-validated inside the script.
- Web traffic (80/443) is unaffected — this manages SSH only.
- Emergency access if you ever lock everything: your host's VNC/web console.
