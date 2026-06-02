<?php

namespace Meta\AdminCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the admin panel to admin-capable users. Alias: `admin-core.admin`.
 *
 * Resolution order (so it works with or without spatie/laravel-permission):
 *   1. must be authenticated;
 *   2. if a Gate ability `admin-core.access-admin` is defined → use it;
 *   3. else if the user has `hasAnyRole()` (spatie) → check the roles in
 *      `admin-core.admin_roles`;
 *   4. else allow any authenticated user (the consumer is responsible for
 *      finer checks).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if(! $user, 403, 'Доступ запрещён.');

        if (\Illuminate\Support\Facades\Gate::has('admin-core.access-admin')) {
            abort_unless(\Illuminate\Support\Facades\Gate::allows('admin-core.access-admin'), 403, 'Доступ запрещён.');

            return $next($request);
        }

        if (method_exists($user, 'hasAnyRole')) {
            $roles = (array) config('admin-core.admin_roles', ['admin', 'super-admin']);
            abort_unless($user->hasAnyRole($roles), 403, 'Доступ запрещён.');
        }

        return $next($request);
    }
}
