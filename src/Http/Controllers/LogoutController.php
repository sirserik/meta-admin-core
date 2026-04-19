<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign the admin user out and send them to the home page.
 *
 * Consumer apps that ship their own auth stack (Breeze, Fortify, …)
 * already route POST /logout to their session controller. The admin
 * SPA, however, is mounted under /admin and its Vue layout posts to
 * /admin/logout — a route the package used to leave undefined. Now we
 * own it so every consumer gets working "выйти" out of the box.
 *
 * Inertia nuance: the admin layout runs inside Inertia, so a plain
 * redirect(302→/) would cause the client to Inertia-fetch `/`, which
 * 404s (the home page is not an Inertia endpoint) or, worse, renders
 * inside the admin shell as a modal. We check X-Inertia and return
 * Inertia::location() (HTTP 409 + X-Inertia-Location) so the browser
 * does a full page load.
 */
class LogoutController extends Controller
{
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            return Inertia::location('/');
        }

        return redirect('/');
    }
}
