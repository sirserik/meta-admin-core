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

    public function test_content_api_is_closed_for_resources_by_default(): void
    {
        $core = new \Meta\AdminCore\AdminCore;
        $core->resource('management', ['model' => 'App\\Models\\Management']);
        $core->resource('news', ['model' => 'App\\Models\\News', 'api' => true]);

        $this->assertFalse($core->getResource('management')['api']);
        $this->assertTrue($core->getResource('news')['api']);
    }

    public function test_every_content_api_action_checks_the_api_flag(): void
    {
        $src = file_get_contents(__DIR__ . '/../../src/Http/Controllers/ContentApiController.php');

        // Ресурс берётся только через apiResource(), который проверяет 'api'.
        $this->assertSame(1, substr_count($src, 'AdminCore::getResource('));
        $this->assertSame(3, substr_count($src, '$this->apiResource($resource)'));
        $this->assertStringContainsString("!empty(\$config['api'])", $src);

        $routes = file_get_contents(__DIR__ . '/../../routes/public.php');
        $this->assertStringContainsString("->middleware('throttle:' . config('admin-core.content_api_throttle'", $routes);
    }

    public function test_hidden_rows_are_not_published(): void
    {
        $src = file_get_contents(__DIR__ . '/../../src/Support/ResourceQuery.php');
        $this->assertStringContainsString("if (self::has(\$table, 'is_active')) \$query->where('is_active', true);", $src);
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
