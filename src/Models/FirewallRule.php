<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A trusted address allowed to reach SSH (port 22).
 *
 * The web admin ONLY writes rows into this table — it never touches the
 * firewall and holds no privilege. A separate root cron job applies the
 * rows to ufw (see the script emitted by
 * `php artisan admin-core:firewall-sync-script`). So a compromised admin
 * panel can at most edit "the list of SSH-allowed addresses", never run
 * privileged commands.
 *
 * Part of the opt-in FirewallFeature — see Meta\AdminCore\Features\FirewallFeature.
 */
class FirewallRule extends Model
{
    protected $fillable = [
        'ip_address',
        'label',
    ];

    /**
     * Validation closure accepting only a well-formed IPv4 address or
     * IPv4/CIDR block. Hard validation matters: the value flows into a
     * system firewall, garbage must never reach it. Reused by the
     * controller and re-checked independently by the root sync script.
     */
    public static function ipOrCidrRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $value = trim((string) $value);
            $ip = $value;

            if (str_contains($value, '/')) {
                [$ip, $prefix] = explode('/', $value, 2);
                if (! ctype_digit($prefix) || (int) $prefix < 0 || (int) $prefix > 32) {
                    $fail('Неверная маска подсети (ожидается /0 … /32).');

                    return;
                }
            }

            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $fail('Введите корректный IPv4-адрес (например 203.0.113.7) или подсеть (203.0.113.0/24).');
            }
        };
    }
}
