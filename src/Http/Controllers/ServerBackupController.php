<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Privilege-isolated server backups (opt-in BackupFeature).
 *
 * The controller performs NO privileged operation: backup/restore requests
 * are dropped as JSON into the spool dir; a ROOT cron agent (emitted by
 * `php artisan admin-core:backup-agent-script`) executes them and writes
 * status.json. Listing / download / delete of backup files the controller
 * does itself (the dirs are group-readable by the web user). Restore always
 * makes a protective pre-restore dump first (in the agent).
 *
 * Named `ServerBackupController` to avoid clashing with the legacy
 * `BackupController` (the simple in-process SQLite/storage zip backup).
 * 404s when the feature is disabled.
 */
class ServerBackupController extends Controller
{
    private function guard(): void
    {
        abort_unless((bool) config('admin-core.features.backup', false), 404);
    }

    private function dbDir(): string    { return rtrim((string) config('admin-core.backup.db_dir'), '/'); }
    private function filesDir(): string { return rtrim((string) config('admin-core.backup.files_dir'), '/'); }
    private function spool(): string    { return rtrim((string) config('admin-core.backup.spool'), '/') . '/requests'; }
    private function statusFile(): string { return rtrim((string) config('admin-core.backup.spool'), '/') . '/status.json'; }

    private function dbRe(): string
    {
        $p = preg_quote((string) config('admin-core.backup.prefix', 'app'), '/');
        return '/^(' . $p . '|pre-restore)-[A-Za-z0-9._-]+\.sql\.gz$/';
    }
    private const FILES_RE = '/^(uploads|code)-[A-Za-z0-9._-]+\.tar\.gz$/';

    public function index()
    {
        $this->guard();

        return view('admin-core::backups', [
            'prefix'      => trim((string) config('admin-core.prefix', 'admin'), '/'),
            'brand'       => config('admin-core.brand.name', 'Admin'),
            'dbBackups'   => $this->listDir($this->dbDir(), $this->dbRe()),
            'fileBackups' => $this->listDir($this->filesDir(), self::FILES_RE),
            'status'      => $this->status(),
            'pending'     => $this->pendingCount(),
            'diskFree'    => @disk_free_space($this->dbDir() ?: '/') ?: 0,
            'diskTotal'   => @disk_total_space($this->dbDir() ?: '/') ?: 0,
        ]);
    }

    public function backup(Request $request)
    {
        $this->guard();
        $type = $request->input('type') === 'files' ? 'backup-files' : 'backup-db';
        $this->queue(['action' => $type]);
        $this->audit("backup.$type", '', $request);

        return back()->with('status', 'Запрос на создание бэкапа поставлен в очередь — выполнится в течение минуты. Обновите страницу.');
    }

    public function restore(Request $request)
    {
        $this->guard();
        $data = $request->validate(['file' => ['required', 'string', 'max:200']]);
        $base = basename($data['file']);
        if (! preg_match($this->dbRe(), $base) || ! is_file($this->dbDir() . '/' . $base)) {
            return back()->withErrors(['file' => 'Недопустимый или несуществующий файл бэкапа.']);
        }
        $this->queue(['action' => 'restore-db', 'file' => $base]);
        $this->audit('backup.restore', $base, $request);

        return back()->with('status', "Восстановление из {$base} поставлено в очередь. Перед восстановлением автоматически создаётся защитная копия текущей БД. Выполнится в течение минуты — обновите страницу.");
    }

    public function download(Request $request)
    {
        $this->guard();
        [$dir, $base] = $this->resolve($request);
        abort_unless($dir !== null && is_file($dir . '/' . $base), 404);

        return response()->download($dir . '/' . $base);
    }

    public function destroy(Request $request)
    {
        $this->guard();
        [$dir, $base] = $this->resolve($request);
        abort_unless($dir !== null && is_file($dir . '/' . $base), 404);
        @unlink($dir . '/' . $base);
        $this->audit('backup.delete', $base, $request);

        return back()->with('status', "Бэкап {$base} удалён.");
    }

    private function resolve(Request $request): array
    {
        $base = basename((string) $request->input('file'));
        $type = $request->input('type');
        if ($type === 'db' && preg_match($this->dbRe(), $base)) {
            return [$this->dbDir(), $base];
        }
        if ($type === 'files' && preg_match(self::FILES_RE, $base)) {
            return [$this->filesDir(), $base];
        }

        return [null, $base];
    }

    private function listDir(string $dir, string $pattern): array
    {
        if (! $dir || ! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || ! preg_match($pattern, $f)) {
                continue;
            }
            $p = $dir . '/' . $f;
            $out[] = ['name' => $f, 'size' => (int) @filesize($p), 'mtime' => (int) @filemtime($p)];
        }
        usort($out, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);

        return $out;
    }

    private function queue(array $payload): void
    {
        $spool = $this->spool();
        if (! is_dir($spool)) {
            return;
        }
        $payload['requested_at'] = now()->toDateTimeString();
        $name = now()->format('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 6) . '.json';
        @file_put_contents($spool . '/' . $name, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function status(): ?array
    {
        $f = $this->statusFile();
        if (! is_file($f)) {
            return null;
        }
        $j = json_decode((string) @file_get_contents($f), true);

        return is_array($j) && ! empty($j) ? $j : null;
    }

    private function pendingCount(): int
    {
        $g = glob($this->spool() . '/*.json');

        return is_array($g) ? count($g) : 0;
    }

    private function audit(string $event, string $file, Request $request): void
    {
        Log::warning(sprintf('[backup] %s file=%s by=%s from=%s', $event, $file, $request->user()?->email ?? 'unknown', $request->ip()));
    }
}
