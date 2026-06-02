<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers + optional CSP and HSTS, all driven by
 * `admin-core.security`. Alias: `admin-core.security-headers`.
 *
 * - The basic headers (nosniff / X-Frame-Options / Referrer-Policy /
 *   Permissions-Policy) are always set.
 * - HSTS is added when `security.hsts` is true (only meaningful over HTTPS).
 * - CSP is emitted only when `security.csp.directives` is non-empty, and is
 *   skipped on the admin prefix (the SPA needs eval/inline; it sits behind
 *   auth). `security.csp.enforce` flips Report-Only → enforcing.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $cfg = (array) config('admin-core.security', []);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $cfg['frame_options'] ?? 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', $cfg['referrer_policy'] ?? 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', $cfg['permissions_policy'] ?? 'camera=(), microphone=(), geolocation=()');

        if (! empty($cfg['hsts']) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $directives = (array) ($cfg['csp']['directives'] ?? []);
        $adminPrefix = trim((string) config('admin-core.prefix', 'admin'), '/');
        if ($directives && ! $request->is($adminPrefix . '/*') && ! $request->is($adminPrefix)) {
            $parts = [];
            foreach ($directives as $name => $value) {
                $parts[] = is_int($name) ? $value : "{$name} {$value}";
            }
            if (! empty($cfg['csp']['report_uri'])) {
                $parts[] = 'report-uri ' . $cfg['csp']['report_uri'];
            }
            $header = ! empty($cfg['csp']['enforce'])
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $response->headers->set($header, implode('; ', $parts));
        }

        return $response;
    }
}
