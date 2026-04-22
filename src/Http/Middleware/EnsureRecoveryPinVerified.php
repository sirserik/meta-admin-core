<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Meta\AdminCore\Http\Controllers\Auth\AdminRecoveryController;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecoveryPinVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $ts  = $request->session()->get(AdminRecoveryController::SESSION_KEY);
        $ttl = (int) config('admin-core.recovery.verified_ttl', 300);

        if (!is_int($ts) || (now()->timestamp - $ts) > $ttl) {
            $request->session()->forget(AdminRecoveryController::SESSION_KEY);
            return redirect()->route('admin-core.recover.pin.form');
        }

        return $next($request);
    }
}
