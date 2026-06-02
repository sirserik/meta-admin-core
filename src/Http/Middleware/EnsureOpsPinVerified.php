<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up PIN gate on top of normal admin auth, for dangerous server-ops
 * pages (firewall, backups). Even a logged-in admin must enter the ops PIN;
 * the unlock lasts `admin-core.ops_pin.ttl` seconds.
 *
 * Safe by default: if no PIN is configured (`admin-core.ops_pin.pin` empty)
 * the gate is a no-op, so adding it to routes never locks anyone out on
 * sites that haven't opted in. Registered as alias `admin-core.ops-pin`.
 */
class EnsureOpsPinVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $pin = (string) config('admin-core.ops_pin.pin', '');

        // No PIN configured → don't gate (avoid accidentally locking out).
        if ($pin === '') {
            return $next($request);
        }

        $at  = (int) $request->session()->get('ops_pin_at', 0);
        $ttl = (int) config('admin-core.ops_pin.ttl', 1800);
        if ($at > 0 && (time() - $at) < $ttl) {
            return $next($request);
        }

        $request->session()->put('ops_pin_intended', $request->fullUrl());

        return redirect()->route('admin-core.ops.unlock');
    }
}
