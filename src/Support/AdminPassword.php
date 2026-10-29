<?php

namespace Meta\AdminCore\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Требования к паролю администратора: длина (по умолчанию 12), буквы
 * в обоих регистрах, цифры и запрет самых частых паролей из словарей
 * перебора. Список короткий намеренно: он ловит «password», «admin123»
 * и подобное, а длину и разнообразие символов проверяет Password.
 */
class AdminPassword
{
    public const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', 'p@ssw0rd', 'qwerty', 'qwerty123',
        'qwertyuiop', '123456', '12345678', '123456789', '1234567890', '11111111', '00000000',
        'admin', 'admin123', 'admin1234', 'administrator', 'root', 'toor', 'changeme', 'welcome',
        'welcome1', 'letmein', 'secret', 'iloveyou', 'abc123', '1q2w3e4r', '1qaz2wsx', 'zaq12wsx',
        'test', 'test123', 'default', 'master', 'superadmin', 'user', 'guest',
    ];

    public static function rule(): Password
    {
        $min = max(8, (int) config('admin-core.password.min', 12));

        return Password::min($min)->letters()->mixedCase()->numbers()
            ->rules([function (string $attribute, mixed $value, \Closure $fail) {
                if (self::isCommon((string) $value)) {
                    $fail('Этот пароль слишком распространён — его подберут первым. Придумайте другой.');
                }
            }]);
    }

    public static function isCommon(string $value): bool
    {
        $v = mb_strtolower(trim($value));
        // «Password2026!» и «Admin123!!» — те же словарные пароли с хвостом.
        $core = preg_replace('/[\d\W_]+$/u', '', $v);

        return in_array($v, self::COMMON, true) || in_array($core, self::COMMON, true);
    }
}
