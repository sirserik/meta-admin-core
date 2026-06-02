<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Unlock / lock the step-up ops-PIN gate (see EnsureOpsPinVerified).
 * Self-contained Blade page (not the SPA) — it guards break-glass tools.
 */
class OpsPinController extends Controller
{
    private function prefix(): string
    {
        return trim((string) config('admin-core.prefix', 'admin'), '/');
    }

    public function showUnlock()
    {
        // PIN not configured → nothing to unlock, go back to the panel.
        if ((string) config('admin-core.ops_pin.pin', '') === '') {
            return redirect('/' . $this->prefix());
        }

        return view('admin-core::ops-unlock', [
            'prefix' => $this->prefix(),
            'brand'  => config('admin-core.brand.name', 'Admin'),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['pin' => ['required', 'string', 'max:64']]);
        $pin = (string) config('admin-core.ops_pin.pin', '');

        if ($pin !== '' && hash_equals($pin, (string) $request->input('pin'))) {
            $request->session()->put('ops_pin_at', time());
            $to = $request->session()->pull('ops_pin_intended');

            return redirect($to ?: '/' . $this->prefix());
        }

        Log::warning(sprintf('[ops-pin] wrong PIN by=%s from=%s', $request->user()?->email ?? 'unknown', $request->ip()));

        return back()->withErrors(['pin' => 'Неверный PIN.']);
    }

    public function lock(Request $request)
    {
        $request->session()->forget('ops_pin_at');

        return redirect()->route('admin-core.ops.unlock')->with('status', 'Доступ к серверным инструментам заблокирован.');
    }
}
