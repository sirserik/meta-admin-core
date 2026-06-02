<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Emits the ROOT backup agent bash script for the BackupFeature, with this
 * site's settings baked in (spool, backup dirs, prefix, retention, files to
 * back up, .env path, app base). DB credentials are read by the script at
 * RUNTIME from .env, so they stay correct if they rotate. DB-agnostic
 * (pgsql / mysql / sqlite).
 *
 * Prints to stdout — install as root (the package never performs the
 * privileged step itself):
 *
 *   sudo php artisan admin-core:backup-agent-script > /usr/local/sbin/admin-core-backup-agent
 *   sudo chmod 700 /usr/local/sbin/admin-core-backup-agent
 *   ( sudo crontab -l 2>/dev/null; echo '* * * * * /usr/local/sbin/admin-core-backup-agent >> /var/log/admin-core-backup.log 2>&1' ) | sudo crontab -
 *
 * Also schedule periodic backups by dropping request files (every 6h):
 * a root cron at minute 0 of hours 0,6,12,18 writes a JSON request like
 * {"action":"backup-db"} into <spool>/requests/ with a unique filename.
 */
class BackupAgentScriptCommand extends Command
{
    protected $signature = 'admin-core:backup-agent-script
                            {--web-group= : Group allowed to read backups (chgrp on status.json); default admin-core.backup.web_group or none}';

    protected $description = 'Печатает root-агент бэкапов для BackupFeature (значения сайта вшиты, креды БД читаются из .env в рантайме; pgsql/mysql/sqlite)';

    public function handle(Filesystem $files): int
    {
        $stubPath = __DIR__ . '/../../../stubs/backup/backup-agent.sh';
        if (! $files->exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");

            return self::FAILURE;
        }

        $filesPaths = (array) config('admin-core.backup.files_paths', ['storage/app', 'public', '.env']);

        $script = strtr($files->get($stubPath), [
            '{{SPOOL}}'       => (string) config('admin-core.backup.spool', '/var/spool/admin-core-ops'),
            '{{DB_DIR}}'      => (string) config('admin-core.backup.db_dir'),
            '{{FILES_DIR}}'   => (string) config('admin-core.backup.files_dir'),
            '{{ENV_FILE}}'    => base_path('.env'),
            '{{APP_BASE}}'    => base_path(),
            '{{PREFIX}}'      => (string) config('admin-core.backup.prefix', 'app'),
            '{{KEEP_DAYS}}'   => (string) (int) config('admin-core.backup.keep_days', 30),
            '{{FILES_PATHS}}' => implode(' ', array_map('trim', $filesPaths)),
            '{{WEB_GROUP}}'   => (string) ($this->option('web-group') ?: config('admin-core.backup.web_group', '')),
        ]);

        $this->getOutput()->writeln($script, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
