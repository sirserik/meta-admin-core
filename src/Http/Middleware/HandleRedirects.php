<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Meta\AdminCore\Models\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply admin-managed URL redirects on GET requests. Alias:
 * `admin-core.redirects`. The active rules are cached (5 min); a matching
 * `from_url` issues `redirect(to_url, status_code)` and bumps `hits`
 * (via the query builder, bypassing model events so it doesn't churn the
 * cache). No-op if the `redirects` table is absent.
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $map = Cache::remember('admin-core:redirects_map', 300, function () {
            if (! Schema::hasTable('redirects')) {
                return [];
            }

            return Redirect::active()
                ->get(['id', 'from_url', 'to_url', 'status_code'])
                ->keyBy('from_url')
                ->toArray();
        });

        $path = '/' . ltrim($request->path(), '/');

        if (isset($map[$path])) {
            $r = $map[$path];
            if (Schema::hasColumn('redirects', 'hits')) {
                DB::table('redirects')->where('id', $r['id'])->increment('hits');
            }

            return redirect($r['to_url'], (int) ($r['status_code'] ?: 301));
        }

        return $next($request);
    }
}
