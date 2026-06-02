<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Emits the ROOT firewall-sync bash script for the FirewallFeature, with
 * this site's settings (emergency IP, .env path, table, ufw comment) baked
 * in. DB credentials are read by the script at RUNTIME from .env, so they
 * stay correct if they rotate.
 *
 * Prints to stdout — install it as root (the package never performs the
 * privileged step itself):
 *
 *   sudo php artisan admin-core:firewall-sync-script > /usr/local/sbin/admin-core-firewall-sync
 *   sudo chmod 700 /usr/local/sbin/admin-core-firewall-sync
 *   ( sudo crontab -l 2>/dev/null; echo '* * * * * /usr/local/sbin/admin-core-firewall-sync >> /var/log/admin-core-firewall.log 2>&1' ) | sudo crontab -
 *
 * Set the emergency IP first so you can never be locked out:
 *   FIREWALL_EMERGENCY_IP=203.0.113.7  (in .env)
 */
class FirewallSyncScriptCommand extends Command
{
    protected $signature = 'admin-core:firewall-sync-script
                            {--emergency= : Override emergency IP (default: admin-core.firewall.emergency_ip)}';

    protected $description = 'Печатает root-скрипт синхронизации ufw для FirewallFeature (значения сайта вшиты, креды БД читаются из .env в рантайме)';

    public function handle(Filesystem $files): int
    {
        $emergency = (string) ($this->option('emergency') ?: config('admin-core.firewall.emergency_ip', ''));

        if ($emergency === '') {
            $this->error('Не задан аварийный IP. Укажи FIREWALL_EMERGENCY_IP в .env или флаг --emergency=<ip> — иначе при пустой таблице потеряешь SSH.');

            return self::FAILURE;
        }

        $stubPath = __DIR__ . '/../../../stubs/firewall/firewall-sync.sh';
        if (! $files->exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");

            return self::FAILURE;
        }

        $script = strtr($files->get($stubPath), [
            '{{EMERGENCY_IP}}' => $emergency,
            '{{ENV_FILE}}'     => base_path('.env'),
            '{{TABLE}}'        => (string) config('admin-core.firewall.table', 'firewall_rules'),
            '{{COMMENT}}'      => (string) config('admin-core.firewall.ufw_comment', 'admin-core-allowlist'),
        ]);

        // Raw script to stdout so it can be piped straight into a file.
        $this->getOutput()->writeln($script, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
