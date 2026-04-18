<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Services\UpdateChecker;
use Symfony\Component\Process\Process;

class UpdateController extends Controller
{
    public function __construct(protected UpdateChecker $checker) {}

    public function index(): Response
    {
        return Inertia::render('Updates/Index', [
            'title'   => 'Обновления',
            'status'  => $this->checker->check(),
            'log'     => $this->latestLog(),
            'capabilities' => $this->probeEnvironment(),
        ]);
    }

    /**
     * Force re-check against GitHub.
     */
    public function check(): RedirectResponse
    {
        $this->checker->clearCache();
        $this->checker->check(true);
        return back()->with('success', 'Проверили GitHub — информация обновлена.');
    }

    /**
     * Run composer update + migrate + caches. Streams output to a log file.
     * Consumer app must have shell_exec/proc_open enabled and composer in PATH.
     */
    public function run(): RedirectResponse
    {
        $logPath = $this->logPath();
        File::ensureDirectoryExists(dirname($logPath));

        $lines = ["=== Update started: " . now()->toDateTimeString() . " ==="];

        // 1. Composer update
        $composer = $this->findComposerBinary();
        if (!$composer) {
            $lines[] = 'ERROR: composer binary not found. Set COMPOSER_PATH in .env.';
            File::put($logPath, implode("\n", $lines));
            return back()->with('error', 'Composer не найден. См. лог.');
        }

        $lines[] = "[1/4] Running: $composer update meta/admin-core -W --no-interaction";
        $ok = $this->runProcess([$composer, 'update', 'meta/admin-core', '-W', '--no-interaction'], base_path(), $lines);
        if (!$ok) {
            File::put($logPath, implode("\n", $lines));
            return back()->with('error', 'Composer update упал. См. лог.');
        }

        // 2. Migrations
        $lines[] = '[2/4] Running: php artisan migrate --force';
        try {
            Artisan::call('migrate', ['--force' => true]);
            $lines[] = Artisan::output();
        } catch (\Throwable $e) {
            $lines[] = 'migrate error: ' . $e->getMessage();
        }

        // 3. Clear caches
        $lines[] = '[3/4] Clearing caches';
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
                $lines[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $lines[] = "$cmd error: " . $e->getMessage();
            }
        }

        // 4. Frontend build — optional, might not be available in prod.
        $npm = $this->findNpmBinary();
        if ($npm && file_exists(base_path('package.json'))) {
            $lines[] = "[4/4] Running: $npm run build";
            $this->runProcess([$npm, 'run', 'build'], base_path(), $lines);
        } else {
            $lines[] = '[4/4] npm not found; rebuild assets manually on the server: npm run build';
        }

        $lines[] = '=== Update finished: ' . now()->toDateTimeString() . ' ===';
        File::put($logPath, implode("\n", $lines));

        $this->checker->clearCache();
        return back()->with('success', 'Обновление выполнено. См. лог внизу.');
    }

    // ---------------------------------------------------------------

    protected function runProcess(array $cmd, string $cwd, array &$lines): bool
    {
        if (!class_exists(Process::class)) {
            $lines[] = 'symfony/process not available';
            return false;
        }
        try {
            $p = new Process($cmd, $cwd, null, null, 600);
            $p->run(function ($type, $buffer) use (&$lines) {
                $lines[] = rtrim($buffer);
            });
            return $p->isSuccessful();
        } catch (\Throwable $e) {
            $lines[] = 'process error: ' . $e->getMessage();
            return false;
        }
    }

    protected function findComposerBinary(): ?string
    {
        $candidates = [
            env('COMPOSER_PATH'),
            '/usr/local/bin/composer',
            '/usr/bin/composer',
            '/opt/homebrew/bin/composer',
            base_path('composer.phar'),
        ];
        foreach ($candidates as $c) {
            if ($c && is_executable($c)) return $c;
        }
        // PATH lookup
        $which = trim((string) @shell_exec('which composer 2>/dev/null'));
        return $which !== '' && is_executable($which) ? $which : null;
    }

    protected function findNpmBinary(): ?string
    {
        $candidates = [env('NPM_PATH'), '/usr/local/bin/npm', '/usr/bin/npm', '/opt/homebrew/bin/npm'];
        foreach ($candidates as $c) {
            if ($c && is_executable($c)) return $c;
        }
        $which = trim((string) @shell_exec('which npm 2>/dev/null'));
        return $which !== '' && is_executable($which) ? $which : null;
    }

    protected function probeEnvironment(): array
    {
        return [
            'composer_found' => (bool) $this->findComposerBinary(),
            'composer_path'  => $this->findComposerBinary(),
            'npm_found'      => (bool) $this->findNpmBinary(),
            'npm_path'       => $this->findNpmBinary(),
            'shell_exec'     => function_exists('shell_exec'),
            'proc_open'      => function_exists('proc_open'),
            'writable_vendor'=> is_writable(base_path('vendor')),
        ];
    }

    protected function logPath(): string
    {
        return storage_path('logs/admin-core-update-' . now()->format('Y-m-d') . '.log');
    }

    protected function latestLog(): ?string
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) return null;
        $files = glob($dir . '/admin-core-update-*.log');
        if (!$files) return null;
        rsort($files);
        $content = @file_get_contents($files[0]);
        return $content ? substr($content, -10000) : null; // tail 10K
    }
}
