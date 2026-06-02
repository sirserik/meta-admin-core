<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Meta\AdminCore\Models\FirewallRule;

/**
 * Manages the list of IPs allowed to reach SSH (port 22).
 *
 * This controller NEVER touches the firewall and never calls sudo — it
 * only reads/writes the `firewall_rules` table. A root cron script
 * (emitted by `php artisan admin-core:firewall-sync-script`) reconciles
 * ufw with the table once a minute. So a compromised admin panel can at
 * most edit "the SSH allow-list", nothing more.
 *
 * Rendered as a self-contained Blade page (NOT the admin SPA) on purpose:
 * it is a break-glass tool — you may need it precisely when the SPA build
 * is broken or your IP changed and you are about to lock yourself out.
 *
 * Part of the opt-in FirewallFeature. The whole page 404s when the
 * feature is disabled, so its routes can be loaded unconditionally.
 */
class FirewallController extends Controller
{
    private function guard(): void
    {
        abort_unless((bool) config('admin-core.features.firewall', false), 404);
    }

    public function index()
    {
        $this->guard();

        return view('admin-core::firewall', [
            'rules'     => FirewallRule::orderByDesc('id')->get(),
            'currentIp' => request()->ip(),
            'prefix'    => trim((string) config('admin-core.prefix', 'admin'), '/'),
            'brand'     => config('admin-core.brand.name', 'Admin'),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'ip_address' => ['required', 'string', 'max:64', FirewallRule::ipOrCidrRule(), Rule::unique('firewall_rules', 'ip_address')],
            'label'      => ['nullable', 'string', 'max:120'],
        ], [], [
            'ip_address' => 'IP-адрес',
            'label'      => 'метка',
        ]);

        $data['ip_address'] = trim($data['ip_address']);

        $rule = FirewallRule::create($data);

        $this->audit('firewall.ip_added', $rule->ip_address, $request);

        return back()->with('status', "Адрес {$rule->ip_address} добавлен. Правило применится к firewall в течение минуты.");
    }

    public function destroy(Request $request, FirewallRule $rule)
    {
        $this->guard();

        $ip = $rule->ip_address;
        $rule->delete();

        $this->audit('firewall.ip_removed', $ip, $request);

        return back()->with('status', "Адрес {$ip} удалён. Доступ для него закроется в течение минуты.");
    }

    private function audit(string $event, string $ip, Request $request): void
    {
        $user = $request->user();
        $line = sprintf('[firewall] %s ip=%s by=%s from=%s', $event, $ip, $user?->email ?? 'unknown', $request->ip());

        Log::warning($line);

        if (function_exists('activity')) {
            try {
                \activity('firewall')
                    ->causedBy($user)
                    ->withProperties(['ip' => $ip, 'event' => $event, 'from' => $request->ip()])
                    ->log($event);
            } catch (\Throwable $e) {
                // activity log is non-critical — the event is already in Log::warning
            }
        }
    }
}
