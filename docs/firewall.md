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
- An **emergency IP** is baked into the script, so the allow-list can never go
  empty and you can never lock yourself out.

The page itself is a **self-contained Blade view, not the admin SPA** — on
purpose. It is a break-glass tool you may need precisely when the SPA build is
broken or your IP just changed.

## Enable

```env
FEATURE_FIREWALL=true
FIREWALL_EMERGENCY_IP=203.0.113.7      # an address you control — NEVER blocked
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
sudo php artisan admin-core:firewall-sync-script > /usr/local/sbin/admin-core-firewall-sync
sudo chmod 700 /usr/local/sbin/admin-core-firewall-sync
( sudo crontab -l 2>/dev/null; \
  echo '* * * * * /usr/local/sbin/admin-core-firewall-sync >> /var/log/admin-core-firewall.log 2>&1' ) \
  | sudo crontab -
```

The generated script has this site's values baked in (emergency IP, `.env`
path, table, ufw comment); **DB credentials are read from `.env` at runtime**,
so they stay correct if they rotate. It supports `pgsql`, `mysql`/`mariadb`
and `sqlite` (read from `DB_CONNECTION`).

> First make sure ufw is active and your current IP is allowed, or add it via
> the page's "Разрешить SSH с моего текущего IP" button before tightening
> `ufw default deny incoming`.

## Config

```php
// config/admin-core.php
'firewall' => [
    'emergency_ip' => env('FIREWALL_EMERGENCY_IP'),   // baked into the script
    'table'        => 'firewall_rules',
    'ufw_comment'  => env('FIREWALL_UFW_COMMENT', 'admin-core-allowlist'),
    'gate'         => env('FIREWALL_GATE_MIDDLEWARE'), // optional step-up middleware
],
```

## Notes

- Only **IPv4** and IPv4/CIDR are accepted (a ufw v4 source rule). Garbage is
  rejected in the controller and independently re-validated in the script.
- Web traffic (80/443) is unaffected — this manages SSH only.
- Emergency access if you ever lock everything: your host's VNC/web console.
