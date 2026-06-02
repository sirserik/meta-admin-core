<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;

/**
 * Diagnose the public storage symlink, permissions and disk — the usual
 * causes of broken/403 media on a fresh deploy. Read-only; prints fixes.
 */
class StorageCheckCommand extends Command
{
    protected $signature = 'admin-core:storage-check';
    protected $description = 'Диагностика storage: симлинк public/storage, права, диск (частые причины 403/битых картинок)';

    public function handle(): int
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        $this->info('Storage diagnostics');
        $this->line(str_repeat('-', 60));

        // 1. symlink
        if (is_link($link)) {
            $dest = readlink($link);
            $this->line("public/storage → symlink → {$dest}");
            is_dir($dest) || $this->warn('  ⚠ target missing — run: php artisan storage:link');
        } elseif (is_dir($link)) {
            $this->warn('public/storage is a REAL directory (not a symlink) — run: php artisan admin-core:storage-relink');
        } else {
            $this->warn('public/storage missing — run: php artisan storage:link');
        }

        // 2. storage/app/public + writable
        if (is_dir($target)) {
            $perms = substr(sprintf('%o', fileperms($target)), -4);
            $this->line("storage/app/public exists (perms {$perms}), " . (is_writable($target) ? 'writable' : 'NOT writable — run: php artisan admin-core:storage-fix-permissions'));
        } else {
            $this->warn('storage/app/public missing — mkdir -p storage/app/public');
        }

        // 3. env
        $this->line('APP_URL: ' . config('app.url'));
        $this->line('FILESYSTEM_DISK: ' . config('filesystems.default'));
        if (config('app.env') === 'production' && str_starts_with((string) config('app.url'), 'http://')) {
            $this->warn('  ⚠ production over http:// — set APP_URL to https://');
        }

        // 4. leftover backup
        if (is_dir(public_path('storage_old_backup'))) {
            $this->warn('Leftover public/storage_old_backup — remove after verifying: php artisan admin-core:storage-cleanup-backup');
        }

        // 5. disk
        $free = @disk_free_space($target ?: base_path());
        if ($free !== false) {
            $this->line('Disk free: ' . round($free / 1073741824, 1) . ' GB');
        }

        return self::SUCCESS;
    }
}
