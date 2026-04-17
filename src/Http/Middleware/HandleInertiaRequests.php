<?php

namespace Meta\AdminCore\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Meta\AdminCore\Facades\AdminCore;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'admin-core::app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [],
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'locale'      => app()->getLocale(),
            'navigation'  => AdminCore::navigation(),
            'brand'       => config('admin-core.brand', [
                'name'  => 'Admin',
                'color' => '#C41E3A',
            ]),
        ];
    }
}
