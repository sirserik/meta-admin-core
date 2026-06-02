<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honeypot spam protection for public forms. Alias: `admin-core.honeypot`.
 *
 * Add a hidden field (default name `website_url`) bots fill but humans
 * don't, plus an optional `_form_time` timestamp; submissions that fill the
 * trap or arrive faster than `security.honeypot.min_seconds` are silently
 * accepted (200/redirect) so bots get no signal. Field name + min time are
 * configurable via `admin-core.security.honeypot`.
 *
 *   <input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">
 *   Route::post('/lead', ...)->middleware('admin-core.honeypot');
 */
class HoneypotProtection
{
    public function handle(Request $request, Closure $next): Response
    {
        $field = (string) config('admin-core.security.honeypot.field', 'website_url');
        $minSeconds = (int) config('admin-core.security.honeypot.min_seconds', 2);

        $tripped = $request->filled($field);

        if (! $tripped && $minSeconds > 0) {
            $ts = $request->input('_form_time');
            if ($ts && (time() - (int) $ts) < $minSeconds) {
                $tripped = true;
            }
        }

        if ($tripped) {
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => 'OK'], 200)
                : redirect()->back();
        }

        return $next($request);
    }
}
