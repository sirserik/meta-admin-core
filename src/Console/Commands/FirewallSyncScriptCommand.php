<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Meta\AdminCore\Models\FirewallRule;

/**
 * Emits the ROOT firewall-sync bash script for the FirewallFeature, with
 * this site's settings (emergency addresses, .env path, table, ufw comment)
 * baked in. DB credentials are read by the script at RUNTIME from .env, so
 * they stay correct if they rotate.
 *
 * Prints to stdout — install it as root (the package never performs the
 * privileged step itself):
 *
 *   sudo php artisan admin-core:firewall-sync-script > /usr/local/sbin/admin-core-firewall-sync
 *   sudo chmod 700 /usr/local/sbin/admin-core-firewall-sync
 *   ( sudo crontab -l 2>/dev/null; echo '* * * * * /usr/local/sbin/admin-core-firewall-sync >> /var/log/admin-core-firewall.log 2>&1' ) | sudo crontab -
 *
 * Set the emergency addresses first so you can never be locked out. A whole
 * block is safer than a single address — a dynamic IP moves within its ISP's
 * range, and one changed octet must not cost you SSH:
 *   FIREWALL_EMERGENCY_IP=37.99.0.0/16,203.0.113.7  (in .env)
 */
class FirewallSyncScriptCommand extends Command
{
    protected $signature = 'admin-core:firewall-sync-script
                            {--emergency= : Override emergency addresses, comma-separated (default: admin-core.firewall.emergency_ip)}
                            {--path= : Write the script to this file (mode 0700) instead of stdout}';

    protected $description = 'Печатает root-скрипт синхронизации ufw для FirewallFeature (значения сайта вшиты, креды БД читаются из .env в рантайме)';

    public function handle(Filesystem $files): int
    {
        $raw = (string) ($this->option('emergency') ?: config('admin-core.firewall.emergency_ip', ''));

        $addresses = array_values(array_filter(array_map(
            'trim',
            preg_split('/[,\s]+/', $raw) ?: [],
        ), static fn (string $v): bool => $v !== ''));

        if ($addresses === []) {
            $this->error('Не задан аварийный адрес. Укажи FIREWALL_EMERGENCY_IP в .env или флаг --emergency=<ip|cidr>[,…] — иначе при пустой таблице потеряешь SSH.');

            return self::FAILURE;
        }

        foreach ($addresses as $address) {
            if (! $this->isValidAddress($address)) {
                $this->error("Аварийный адрес '{$address}' — не IPv4 и не IPv4/CIDR. Значение уходит прямо в ufw, мусор туда попасть не должен.");

                return self::FAILURE;
            }
        }

        $stubPath = __DIR__ . '/../../../stubs/firewall/firewall-sync.sh';
        if (! $files->exists($stubPath)) {
            $this->error("Stub not found: {$stubPath}");

            return self::FAILURE;
        }

        $script = strtr($files->get($stubPath), [
            '{{EMERGENCY_IPS}}' => implode("\n", $addresses),
            '{{ENV_FILE}}'      => base_path('.env'),
            '{{TABLE}}'         => (string) config('admin-core.firewall.table', 'firewall_rules'),
            '{{COMMENT}}'       => (string) config('admin-core.firewall.ufw_comment', 'admin-core-allowlist'),
        ]);

        // Writing the file ourselves beats `> file`: anything PHP prints before
        // the command runs — a deprecation notice from a config file, a warning
        // from an extension — lands in the redirect too and leaves the script
        // with garbage above its shebang.
        if ($path = (string) $this->option('path')) {
            $files->put($path, $script);
            $files->chmod($path, 0700);
            $this->info("Скрипт записан: {$path} (режим 0700)");

            return self::SUCCESS;
        }

        // Raw script to stdout so it can be piped straight into a file.
        $this->getOutput()->writeln($script, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }

    /** Same rule the admin form and the bash script apply, so all three agree. */
    private function isValidAddress(string $address): bool
    {
        $failed = false;
        (FirewallRule::ipOrCidrRule())('ip_address', $address, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
