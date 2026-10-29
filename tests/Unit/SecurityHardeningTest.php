<?php

namespace Tests\Unit;

use Meta\AdminCore\Support\AdminPassword;
use PHPUnit\Framework\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_dictionary_passwords_are_rejected_even_with_suffix(): void
    {
        foreach (['password', 'Password', 'PASSWORD123', 'Password2026!', 'admin123', 'Admin123!!', 'qwerty_2025'] as $p) {
            $this->assertTrue(AdminPassword::isCommon($p), $p);
        }
    }

    public function test_real_passwords_pass_the_dictionary_check(): void
    {
        foreach (['Kq7mZr2vXa9w', 'Сирень-Апрель-2026', 'password-manager-Xy7'] as $p) {
            $this->assertFalse(AdminPassword::isCommon($p), $p);
        }
    }

    public function test_zip_backup_and_web_updater_routes_are_behind_config(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../routes/admin.php');

        foreach (['zip_backup' => 'BackupController', 'web_updater' => 'UpdateController'] as $flag => $controller) {
            $guard = strpos($routes, "if (config('admin-core.{$flag}', false)) {");
            $this->assertNotFalse($guard, "нет проверки admin-core.{$flag}");
            $close = strpos($routes, "\n    }\n", $guard);
            $inside = substr($routes, $guard, $close - $guard);
            $this->assertSame(
                substr_count($routes, $controller . '::class'),
                substr_count($inside, $controller . '::class'),
                "маршруты {$controller} должны быть только внутри проверки"
            );
        }
    }

    public function test_dangerous_tools_are_off_by_default(): void
    {
        $config = require __DIR__ . '/../../config/admin-core.php';
        // env() вне Laravel возвращает значение по умолчанию.
        $this->assertFalse($config['zip_backup']);
        $this->assertFalse($config['web_updater']);
        $this->assertGreaterThanOrEqual(12, (int) $config['password']['min']);
    }
}
