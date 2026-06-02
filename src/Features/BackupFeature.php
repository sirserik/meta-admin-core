<?php

namespace Meta\AdminCore\Features;

use Meta\AdminCore\AdminCore;

/**
 * Privilege-isolated server backups — create/download/restore DB & file
 * backups from the admin panel without giving the web process any
 * privilege. Same architecture as FirewallFeature:
 *
 *   - the admin page only drops JSON request files into a spool dir;
 *   - a ROOT cron agent (emitted by `admin-core:backup-agent-script`)
 *     runs the dumps/restores and writes status.json — root never
 *     executes application PHP;
 *   - restore always takes a protective pre-restore dump first.
 *
 * DB-agnostic agent (pgsql / mysql / sqlite). Supersedes the legacy
 * in-process `BackupController` (the simple SQLite/storage zip at
 * `/{prefix}/backup`), which stays for managed/Plesk single-node installs.
 *
 * Self-contained Blade page at `/{prefix}/backups`, gated by admin auth +
 * the step-up ops-PIN. Enable with `FEATURE_BACKUP=true`. The one manual
 * (privileged) step is installing the root cron agent — see docs/backups.md.
 */
class BackupFeature extends FeatureModule
{
    public function name(): string        { return 'backup'; }
    public function label(): string       { return 'Бэкапы сервера'; }
    public function description(): string { return 'Создание/скачивание/восстановление бэкапов БД и файлов из админки. PHP только ставит задачу в spool — выполняет root-cron агент (привилегий у веб-процесса нет), restore делает защитную копию. DB-агностично (pgsql/mysql/sqlite).'; }
    public function icon(): string        { return 'fa-database'; }

    public function register(AdminCore $core): void
    {
        $prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

        $core->menuItem(
            'Бэкапы',
            "/{$prefix}/backups",
            'fa-database',
            'Сервер',
            91,
        );
    }
}
