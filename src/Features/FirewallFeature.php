<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;

/**
 * SSH firewall allow-list — manage from the admin panel which IPs may
 * reach SSH (port 22), without ever giving the web process any privilege.
 *
 * Architecture (the reusable, security-conscious part):
 *   - the admin page ONLY writes rows into `firewall_rules`;
 *   - a root cron job reconciles ufw with that table once a minute
 *     (script emitted by `php artisan admin-core:firewall-sync-script`);
 *   - an EMERGENCY_IP is baked into the script, so the list can never go
 *     empty and you cannot lock yourself out;
 *   - root never executes any application PHP — smallest attack surface.
 *
 * Self-registers a sidebar link to the self-contained `/admin/firewall`
 * Blade page (deliberately NOT the SPA — it is a break-glass tool that
 * must work even if the SPA build is broken).
 *
 * Self-contained: the package ships the model + migration + controller +
 * view, so `available()` is always true. The ONE manual step is installing
 * the root cron sync script (a privileged op the package cannot and should
 * not perform from PHP) — see the command above and docs/firewall.md.
 *
 * Enable with `FEATURE_FIREWALL=true` (or the `feature.firewall` setting).
 * Meant for self-hosted nodes with ufw; no-op / hide on managed hosting.
 */
class FirewallFeature extends FeatureModule
{
    public function name(): string        { return 'firewall'; }
    public function label(): string       { return 'SSH Firewall'; }
    public function description(): string { return 'Управление списком IP для входа по SSH (порт 22) прямо из админки. PHP только пишет в таблицу — применяет ufw root-cron, привилегий у веб-процесса нет. Аварийный IP зашит в скрипт (лок-аут невозможен).'; }
    public function icon(): string        { return 'fa-shield-halved'; }

    public function register(AdminCore $core): void
    {
        $prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

        $core->menuItem(
            'SSH-доступ',
            "/{$prefix}/firewall",
            'fa-shield-halved',
            'Сервер',
            90,
        );
    }
}
