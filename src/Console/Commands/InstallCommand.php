<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'admin-core:install
        {--force : Overwrite existing files}
        {--no-npm : Skip npm install + build}
        {--no-user : Skip default admin user creation}';

    protected $description = 'One-step setup: publish views/config, scaffold Vite entries, register Inertia middleware, run migrations, create admin user';

    public function handle(): int
    {
        $this->output->write($this->banner());

        $this->step(1, 'Публикую конфиг и root view');
        $this->publishPackageAssets();

        $this->step(2, 'Разворачиваю Vite entry (admin-spa.js + admin-spa.css)');
        $this->writeViteEntries();

        $this->step(3, 'Копирую layout для Inertia (resources/views/admin/inertia.blade.php)');
        $this->writeInertiaRootView();

        $this->step(4, 'Проверяю middleware HandleInertiaRequests в bootstrap/app.php');
        $this->ensureMiddleware();

        $this->step(5, 'Проверяю Vite config (preserveSymlinks + @admin-core alias)');
        $this->ensureViteConfig();

        $this->step(6, 'Запускаю миграции');
        Artisan::call('migrate', ['--force' => true], $this->output);

        if (!$this->option('no-user')) {
            $this->step(7, 'Создаю администратора');
            $this->createAdminUser();
        }

        if (!$this->option('no-npm')) {
            $this->step(8, 'Устанавливаю npm-зависимости + сборка');
            $this->installNpmDeps();
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Установка завершена.</>');
        $this->newLine();
        $this->line('  Открой: <fg=cyan;options=bold>' . url('/admin') . '</>');
        if (!$this->option('no-user')) {
            $this->line('  Логин:  <fg=cyan>admin@example.com</> / <fg=cyan>password</>');
        }
        $this->line('  Доки:   <fg=cyan>https://github.com/sirserik/meta-admin-core/tree/main/docs</>');
        $this->newLine();
        $this->line('  Зарегистрируй ресурсы в <fg=yellow>app/Providers/AppServiceProvider.php::boot()</>:');
        $this->newLine();
        $this->line('    <fg=gray>use Meta\\AdminCore\\Facades\\AdminCore;</>');
        $this->line('    <fg=gray>AdminCore::resource(\'articles\', [\'model\' => \\App\\Models\\Article::class, …]);</>');
        $this->newLine();

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------

    protected function step(int $n, string $title): void
    {
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>[{$n}]</> {$title}");
    }

    protected function publishPackageAssets(): void
    {
        $params = ['--provider' => 'Meta\\AdminCore\\AdminCoreServiceProvider'];
        if ($this->option('force')) $params['--force'] = true;
        Artisan::call('vendor:publish', $params + ['--tag' => 'admin-core-config']);
        $this->line('    ✓ config/admin-core.php');
    }

    protected function writeViteEntries(): void
    {
        $stubRoot = __DIR__ . '/../../../stubs';

        $jsPath  = base_path('resources/js/admin-spa.js');
        $cssPath = base_path('resources/css/admin-spa.css');

        File::ensureDirectoryExists(dirname($jsPath));
        File::ensureDirectoryExists(dirname($cssPath));

        $this->copyIfNew($stubRoot . '/admin-spa.js', $jsPath, 'resources/js/admin-spa.js');

        // CSS: detect Tailwind version and pick right snippet.
        $css = File::get($stubRoot . '/admin-spa.css');
        if ($this->detectTailwindV4()) {
            $css = str_replace(
                ['@tailwind base;', '@tailwind components;', '@tailwind utilities;'],
                '/* v3 directives disabled — using Tailwind v4 */',
                $css,
            );
            $css = str_replace('/*\n@import "tailwindcss";', '@import "tailwindcss";', $css);
            $css = str_replace('@source "../../vendor/meta/admin-core/resources/js";', '@source "../../vendor/meta/admin-core/resources/js";', $css);
            $css = preg_replace('/^@tailwind.*$/m', '', $css);
        }
        $this->writeIfNew($cssPath, $css, 'resources/css/admin-spa.css');

        // Site pages folder (empty, for future overrides).
        File::ensureDirectoryExists(base_path('resources/js/admin-spa/pages'));
        $keep = base_path('resources/js/admin-spa/pages/.gitkeep');
        if (!file_exists($keep)) file_put_contents($keep, '');
    }

    protected function writeInertiaRootView(): void
    {
        $stub = __DIR__ . '/../../../stubs/admin.inertia.blade.php';
        $target = base_path('resources/views/admin/inertia.blade.php');
        File::ensureDirectoryExists(dirname($target));
        $this->copyIfNew($stub, $target, 'resources/views/admin/inertia.blade.php');
    }

    protected function ensureMiddleware(): void
    {
        $bootPath = base_path('bootstrap/app.php');
        if (!file_exists($bootPath)) {
            $this->warn('    bootstrap/app.php не найден — добавь HandleInertiaRequests вручную.');
            return;
        }
        $content = file_get_contents($bootPath);
        $needle = 'Meta\\AdminCore\\Http\\Middleware\\HandleInertiaRequests';
        if (str_contains($content, $needle) || str_contains($content, 'App\\Http\\Middleware\\HandleInertiaRequests')) {
            $this->line('    ✓ уже подключен');
            return;
        }
        $append = <<<'PHP'
        $middleware->web(append: [
            \Meta\AdminCore\Http\Middleware\HandleInertiaRequests::class,
        ]);
PHP;
        // Best-effort insertion. Look for ->withMiddleware(function (Middleware $middleware) {
        if (preg_match('/->withMiddleware\(function \(Middleware \$middleware\)[^{]*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $content = substr($content, 0, $pos) . "\n" . $append . "\n" . substr($content, $pos);
            file_put_contents($bootPath, $content);
            $this->line('    ✓ вставил в withMiddleware');
        } else {
            $this->warn('    не нашёл withMiddleware() — добавь вручную:');
            $this->line("        <fg=gray>{$append}</>");
        }
    }

    protected function ensureViteConfig(): void
    {
        $vitePath = base_path('vite.config.js');
        if (!file_exists($vitePath)) {
            $this->warn('    vite.config.js не найден — пропускаю');
            return;
        }
        $content = file_get_contents($vitePath);
        $changed = false;

        // Add admin-spa entries to laravel({input: [...]})
        if (!str_contains($content, 'admin-spa.js')) {
            $content = preg_replace(
                "/(input:\\s*\\[)/",
                "$1\n                'resources/css/admin-spa.css',\n                'resources/js/admin-spa.js',",
                $content,
                1,
            );
            $changed = true;
            $this->line('    ✓ добавил admin-spa.{css,js} в laravel().input');
        }

        // Ensure resolve block
        if (!str_contains($content, 'preserveSymlinks')) {
            $inject = <<<'JS'
    resolve: {
        preserveSymlinks: true,
        alias: {
            '@admin-core': '/vendor/meta/admin-core/resources/js',
        },
    },
JS;
            $content = preg_replace(
                "/(plugins:\\s*\\[[\\s\\S]*?\\],)/",
                "$1\n" . $inject,
                $content,
                1,
            );
            $changed = true;
            $this->line('    ✓ добавил resolve.preserveSymlinks + alias');
        }

        if ($changed) file_put_contents($vitePath, $content);
        else          $this->line('    ✓ уже сконфигурирован');
    }

    protected function createAdminUser(): void
    {
        if (!Schema::hasTable('users')) {
            $this->warn('    таблица users отсутствует — скипаю');
            return;
        }
        $userClass = config('auth.providers.users.model', 'App\\Models\\User');
        if (!class_exists($userClass)) {
            $this->warn('    модель ' . $userClass . ' не найдена — скипаю');
            return;
        }
        if ($userClass::where('email', 'admin@example.com')->exists()) {
            $this->line('    ✓ admin@example.com уже существует');
            return;
        }
        $userClass::create([
            'name'              => 'Admin',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $this->line('    ✓ admin@example.com / password');
    }

    protected function installNpmDeps(): void
    {
        $deps = [
            'vue', '@inertiajs/vue3', '@vitejs/plugin-vue',
            '@tiptap/vue-3', '@tiptap/starter-kit',
            '@tiptap/extension-link', '@tiptap/extension-image', '@tiptap/extension-underline',
            '@fontsource/inter', '@fortawesome/fontawesome-free',
        ];
        $pkg = base_path('package.json');
        $existing = file_exists($pkg) ? (json_decode(file_get_contents($pkg), true) ?? []) : [];
        $have = array_merge($existing['dependencies'] ?? [], $existing['devDependencies'] ?? []);
        $missing = array_values(array_filter($deps, fn ($d) => !isset($have[$d])));

        if ($missing) {
            $this->line('    Missing: ' . implode(', ', $missing));
            $this->runSh(array_merge(['npm', 'install', '--no-audit', '--no-fund', '--silent'], $missing));
        } else {
            $this->line('    ✓ все зависимости уже есть');
        }
        $this->line('    Сборка фронта…');
        $this->runSh(['npm', 'run', 'build']);
    }

    protected function copyIfNew(string $from, string $to, string $label): void
    {
        if (file_exists($to) && !$this->option('force')) {
            $this->line("    ~ пропущен (уже есть): {$label}");
            return;
        }
        File::copy($from, $to);
        $this->line("    ✓ {$label}");
    }

    protected function writeIfNew(string $to, string $content, string $label): void
    {
        if (file_exists($to) && !$this->option('force')) {
            $this->line("    ~ пропущен (уже есть): {$label}");
            return;
        }
        File::put($to, $content);
        $this->line("    ✓ {$label}");
    }

    protected function detectTailwindV4(): bool
    {
        $pkg = base_path('package.json');
        if (!file_exists($pkg)) return false;
        $j = json_decode(file_get_contents($pkg), true);
        $all = array_merge($j['dependencies'] ?? [], $j['devDependencies'] ?? []);
        $tw = $all['tailwindcss'] ?? null;
        if (!$tw) return false;
        return str_starts_with(ltrim($tw, '^~'), '4');
    }

    protected function runSh(array $cmd): void
    {
        try {
            $p = new Process($cmd, base_path(), null, null, 300);
            $p->run(function ($type, $buf) { $this->output->write($buf); });
            if (!$p->isSuccessful()) $this->error('    команда упала: ' . implode(' ', $cmd));
        } catch (\Throwable $e) {
            $this->error('    ' . $e->getMessage());
        }
    }

    protected function banner(): string
    {
        return <<<'T'

  <fg=red;options=bold>╔═══════════════════════════════════════════════╗
  ║  meta/admin-core — one-command installation   ║
  ╚═══════════════════════════════════════════════╝</>

T;
    }
}
