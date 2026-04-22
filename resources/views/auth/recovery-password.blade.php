@component('admin-core::auth._layout', [
    'title' => __('Новый пароль'),
    'lead'  => __('PIN-код подтверждён. Задайте новый пароль администратора.'),
    'icon'  => '&#128274;',
])
<form method="POST" action="{{ route('admin-core.recover.password.update') }}" autocomplete="off">
    @csrf
    <div class="field">
        <label class="label" for="email">{{ __('Email администратора') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required
               autocomplete="username" class="input">
    </div>

    <div class="field">
        <label class="label" for="password">{{ __('Новый пароль') }}</label>
        <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
        <p class="hint">
            {{ __('Минимум :n символов, буквы и цифры.', ['n' => config('admin-core.recovery.password_min', 12)]) }}
        </p>
    </div>

    <div class="field">
        <label class="label" for="password_confirmation">{{ __('Повторите пароль') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required
               autocomplete="new-password" class="input">
    </div>

    <button type="submit" class="btn">{{ __('Сохранить пароль') }}</button>
</form>
@endcomponent
