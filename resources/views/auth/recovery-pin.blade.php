@component('admin-core::auth._layout', [
    'title' => __('Восстановление доступа'),
    'lead'  => __('Введите PIN-код для смены пароля администратора.'),
    'icon'  => '&#128273;',
])
<form method="POST" action="{{ route('admin-core.recover.pin.verify') }}" autocomplete="off">
    @csrf
    <div class="field">
        <label class="label" for="pin">{{ __('PIN-код') }}</label>
        <input id="pin" name="pin" type="password" required autofocus autocomplete="one-time-code"
               maxlength="128" class="input mono">
    </div>
    <button type="submit" class="btn">{{ __('Продолжить') }}</button>
</form>
@endcomponent
