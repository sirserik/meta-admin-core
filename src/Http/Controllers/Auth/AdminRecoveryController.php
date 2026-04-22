<?php

namespace Meta\AdminCore\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Off-band admin password recovery (PIN-gated).
 *
 * Rescues locked-out admins without relying on the normal mail-based reset
 * flow (unavailable inbox, SMTP down, tight deadline). Feature self-disables
 * by 404 when the PIN isn't configured, so consumer sites that don't opt
 * in never advertise the endpoint.
 *
 * See config/admin-core.php -> `recovery`.
 */
class AdminRecoveryController extends Controller
{
    public const SESSION_KEY = 'admin_core_recovery_verified_at';

    public function showPinForm(): View
    {
        $this->ensureEnabled();
        return view('admin-core::auth.recovery-pin');
    }

    public function verifyPin(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $request->validate([
            'pin' => ['required', 'string', 'max:128'],
        ]);

        $expected = (string) config('admin-core.recovery.pin');
        $given    = (string) $request->input('pin');

        if (!hash_equals(hash('sha256', $expected), hash('sha256', $given))) {
            Log::warning('admin-core recovery: bad PIN', [
                'ip' => $request->ip(),
                'ua' => substr((string) $request->userAgent(), 0, 200),
            ]);
            throw ValidationException::withMessages([
                'pin' => __('Неверный PIN-код.'),
            ]);
        }

        $request->session()->put(self::SESSION_KEY, now()->timestamp);
        $request->session()->regenerate();

        Log::info('admin-core recovery: PIN verified', ['ip' => $request->ip()]);

        return redirect()->route('admin-core.recover.password.form');
    }

    public function showPasswordForm(): View
    {
        $this->ensureEnabled();
        return view('admin-core::auth.recovery-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $min  = (int) config('admin-core.recovery.password_min', 12);
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min($min)->letters()->numbers()],
        ]);

        $userModel = $this->userModel();
        $user      = $userModel::where('email', $data['email'])->first();

        if (!$user || !$this->userIsAllowed($user)) {
            Log::warning('admin-core recovery: reset refused', [
                'ip'          => $request->ip(),
                'email_given' => $data['email'],
                'reason'      => $user ? 'wrong-role' : 'not-found',
            ]);
            throw ValidationException::withMessages([
                'email' => __('Пользователь не найден или недостаточно прав.'),
            ]);
        }

        $user->forceFill([
            'password'       => Hash::make($data['password']),
            'remember_token' => null,
        ])->save();

        Log::notice('admin-core recovery: password reset', [
            'ip'      => $request->ip(),
            'user_id' => $user->getKey(),
            'email'   => $user->email,
        ]);

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        $loginRoute = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/';

        return redirect($loginRoute)
            ->with('status', __('Пароль изменён. Войдите с новым паролем.'));
    }

    public static function isEnabled(): bool
    {
        $pin = (string) config('admin-core.recovery.pin');
        $min = (int) config('admin-core.recovery.min_pin_length', 8);
        return $pin !== '' && mb_strlen($pin) >= $min;
    }

    protected function ensureEnabled(): void
    {
        abort_if(!self::isEnabled(), 404);
    }

    protected function userModel(): string
    {
        return config('auth.providers.users.model', \App\Models\User::class);
    }

    protected function userIsAllowed(Model $user): bool
    {
        $allowed = (array) config('admin-core.recovery.allowed_roles', []);
        if ($allowed === []) {
            return true;
        }
        if (!method_exists($user, 'hasAnyRole')) {
            return true;
        }
        return $user->hasAnyRole($allowed);
    }
}
